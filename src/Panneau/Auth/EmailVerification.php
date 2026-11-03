<?php

namespace Panneau\Auth;

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Carbon;

/**
 * Email verification of the `panneau` auth mode (panneau-js decision 0048): which users must
 * verify their address, and the tokens of the links. A token is opaque to the app: the id of the
 * user, a hash of their address and an expiry, encrypted with the key of the app. Having it
 * proves the address, without a session.
 *
 * @internal
 */
final class EmailVerification
{
    public function __construct(
        private readonly Config $config,
        private readonly Encrypter $encrypter,
        private readonly AuthManager $auth,
    ) {}

    public function enabled(): bool
    {
        return $this->config->get('panneau.auth.mode') === 'panneau'
            && $this->config->get('panneau.auth.email_verification') === true;
    }

    /**
     * Whether the API refuses the user until they verify their address.
     *
     * @phpstan-assert-if-true MustVerifyEmail&Authenticatable $user
     */
    public function mustVerify(?Authenticatable $user): bool
    {
        return $this->enabled() && $user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail();
    }

    public function token(MustVerifyEmail&Authenticatable $user): string
    {
        $minutes = $this->config->get('auth.verification.expire', 60);

        return $this->encrypter->encryptString((string) json_encode([
            'id' => $user->getAuthIdentifier(),
            'hash' => sha1($user->getEmailForVerification()),
            'expires' => Carbon::now()->addMinutes(is_numeric($minutes) ? (int) $minutes : 60)->getTimestamp(),
        ]));
    }

    /**
     * The user of a token that hasn't expired and whose address didn't change since, or null.
     */
    public function userOf(string $token): (MustVerifyEmail&Authenticatable)|null
    {
        try {
            $payload = json_decode($this->encrypter->decryptString($token), true);
        } catch (DecryptException) {
            return null;
        }
        if (! is_array($payload) || ! is_int($payload['expires'] ?? null) || ! is_string($payload['hash'] ?? null)
            || ! (is_int($payload['id'] ?? null) || is_string($payload['id'] ?? null))
            || $payload['expires'] < Carbon::now()->getTimestamp()) {
            return null;
        }
        $user = $this->auth->createUserProvider($this->provider())?->retrieveById($payload['id']);

        return $user instanceof MustVerifyEmail && hash_equals(sha1($user->getEmailForVerification()), $payload['hash'])
            ? $user
            : null;
    }

    /**
     * The user provider of the guard of `panneau.auth.guard`: the default provider for a guard
     * without one, such as a guard of `Auth::viaRequest()`.
     */
    private function provider(): ?string
    {
        $guard = $this->config->get('panneau.auth.guard');
        $guard = is_string($guard) ? $guard : $this->config->get('auth.defaults.guard');
        $provider = is_string($guard) ? $this->config->get("auth.guards.{$guard}.provider") : null;

        return is_string($provider) ? $provider : null;
    }
}
