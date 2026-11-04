<?php

namespace Panneau\Http\Controllers;

use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use LogicException;
use Panneau\Auth\PasswordConfirmation;
use Panneau\Auth\TwoFactor;
use Panneau\Definition\DefinitionBuilder;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Two-factor authentication of the `panneau` auth mode, with `panneau.auth.two_factor`
 * (panneau-js decision 0049), on Fortify's trait, actions and TOTP provider, without its routes:
 *
 * - `POST /auth/two-factor/challenge` ends a login that `AuthController` left waiting;
 * - `/account/two-factor`: the status of the current user, the setup and its confirmation, new
 *   recovery codes, and turning it off. The changes need a recent password (`RequirePassword`).
 *
 * @internal
 */
final class TwoFactorController
{
    /**
     * The login that waits for its second factor, in the session: the id of the user, and whether
     * to remember them.
     */
    public const PENDING_LOGIN = 'panneau.auth.login';

    public function __construct(
        private readonly Auth $auth,
        private readonly Config $config,
        private readonly Container $container,
        private readonly TwoFactor $twoFactor,
        private readonly TwoFactorAuthenticationProvider $provider,
        private readonly PasswordConfirmation $confirmation,
        private readonly DefinitionBuilder $definition,
    ) {}

    public function challenge(Request $request): JsonResponse
    {
        $answer = $request->validate([
            'code' => ['required_without:recoveryCode', 'nullable', 'string'],
            'recoveryCode' => ['required_without:code', 'nullable', 'string'],
        ]);
        $pending = $request->hasSession() ? $request->session()->get(self::PENDING_LOGIN) : null;
        $guard = is_array($pending) && isset($pending['id']) ? $this->guard() : null;
        $user = $guard?->getProvider()->retrieveById($pending['id']);
        if (! $user instanceof Model || $this->twoFactor->status($user) !== 'enabled') {
            throw ValidationException::withMessages(['code' => __('The login has expired. Log in again.')]);
        }

        $recoveryCode = $answer['recoveryCode'] ?? null;
        if (is_string($recoveryCode) && $recoveryCode !== '') {
            if (! $this->twoFactor->useRecoveryCode($user, $recoveryCode)) {
                throw ValidationException::withMessages(['recoveryCode' => __('The provided two factor recovery code was invalid.')]);
            }
            event(new RecoveryCodeReplaced($user, $recoveryCode));
        } elseif (! $this->provider->verify($this->twoFactor->secret($user), (string) ($answer['code'] ?? ''))) {
            throw ValidationException::withMessages(['code' => __('The provided two factor authentication code was invalid.')]);
        }

        $request->session()->forget(self::PENDING_LOGIN);
        /** @var SessionGuard $guard */
        $guard->login($user, ($pending['remember'] ?? false) === true);
        $request->session()->regenerate();
        $this->confirmation->confirm($request);

        return new JsonResponse(['user' => $this->definition->user($user)]);
    }

    public function show(Request $request): JsonResponse
    {
        $user = $this->user($request);

        return new JsonResponse(['status' => $this->twoFactor->status($user), 'required' => $this->twoFactor->required($user)]);
    }

    public function enable(Request $request): JsonResponse
    {
        $user = $this->supportedUser($request);
        if ($this->twoFactor->status($user) === 'enabled') {
            throw new ConflictHttpException(__('Two-factor authentication is already on.'));
        }
        // A new secret for each setup, until one is confirmed
        $this->container->make(EnableTwoFactorAuthentication::class)($user, true);

        return new JsonResponse([
            'secret' => $this->twoFactor->secret($user),
            'otpauthUrl' => $this->twoFactor->otpauthUrl($user),
            'qrCode' => $this->twoFactor->qrCodeSvg($user),
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $code = (string) $request->validate(['code' => ['required', 'string']])['code'];
        $user = $this->supportedUser($request);
        if ($this->twoFactor->status($user) !== 'pending') {
            throw new ConflictHttpException(__('Two-factor authentication isn\'t being set up.'));
        }
        $this->container->make(ConfirmTwoFactorAuthentication::class)($user, $code);

        return new JsonResponse(['recoveryCodes' => $this->twoFactor->recoveryCodes($user)]);
    }

    public function recoveryCodes(Request $request): JsonResponse
    {
        $user = $this->supportedUser($request);
        if ($this->twoFactor->status($user) !== 'enabled') {
            throw new ConflictHttpException(__('Two-factor authentication is off.'));
        }
        $this->container->make(GenerateNewRecoveryCodes::class)($user);

        return new JsonResponse(['recoveryCodes' => $this->twoFactor->recoveryCodes($user)]);
    }

    public function disable(Request $request): Response
    {
        $user = $this->supportedUser($request);
        if ($this->twoFactor->required($user)) {
            throw new ConflictHttpException(__('Two-factor authentication is required for this account.'));
        }
        $this->container->make(DisableTwoFactorAuthentication::class)($user);

        return new Response(status: 204);
    }

    private function user(Request $request): Authenticatable
    {
        $user = $request->user();
        if (! $user instanceof Authenticatable) {
            throw new LogicException('The two-factor routes need the user of the panneau.auth middleware.');
        }

        return $user;
    }

    /**
     * @return Authenticatable&Model
     */
    private function supportedUser(Request $request): Authenticatable
    {
        $user = $this->user($request);
        if (! $this->twoFactor->supports($user) || ! $user instanceof Model) {
            throw new ConflictHttpException(__('This account can\'t have two-factor authentication.'));
        }

        return $user;
    }

    private function guard(): SessionGuard
    {
        $name = $this->config->get('panneau.auth.guard');
        $guard = $this->auth->guard(is_string($name) ? $name : null);
        if (! $guard instanceof SessionGuard) {
            throw new LogicException('Two-factor authentication needs a session guard: set panneau.auth.guard to one.');
        }

        return $guard;
    }
}
