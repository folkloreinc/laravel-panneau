<?php

namespace Panneau\Auth;

use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Model;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Panneau\Roles\RoleManager;

/**
 * Two-factor authentication of the `panneau` auth mode (panneau-js decision 0049), on Fortify's
 * `TwoFactorAuthenticatable` trait and its `two_factor_*` columns. It is on once confirmed with a
 * code, whatever the site's Fortify features say.
 *
 * Who must have it: `panneau.auth.two_factor_required` (true for everyone, or a Gate ability
 * that the users who must have it are allowed), and the roles of the roles module whose
 * `two_factor` is true.
 *
 * @internal
 */
final class TwoFactor
{
    public function __construct(
        private readonly Config $config,
        private readonly Gate $gate,
        private readonly RoleManager $roles,
        private readonly TwoFactorAuthenticationProvider $provider,
    ) {}

    public function enabled(): bool
    {
        return $this->config->get('panneau.auth.mode') === 'panneau'
            && $this->config->get('panneau.auth.two_factor') === true;
    }

    /**
     * Whether the user can have it: its class uses Fortify's trait.
     */
    public function supports(?Authenticatable $user): bool
    {
        return $user !== null && in_array(TwoFactorAuthenticatable::class, class_uses_recursive($user), true);
    }

    /**
     * @return 'disabled'|'pending'|'enabled'
     */
    public function status(?Authenticatable $user): string
    {
        if (! $this->supports($user) || empty(data_get($user, 'two_factor_secret'))) {
            return 'disabled';
        }

        return data_get($user, 'two_factor_confirmed_at') !== null ? 'enabled' : 'pending';
    }

    public function required(?Authenticatable $user): bool
    {
        if ($user === null || ! $this->enabled()) {
            return false;
        }
        $required = $this->config->get('panneau.auth.two_factor_required', false);
        if ($required === true || (is_string($required) && $required !== '' && $this->gate->forUser($user)->allows($required))) {
            return true;
        }

        return $this->config->get('panneau.roles.enabled') === true && $this->roles->requiresTwoFactor($user);
    }

    /**
     * The secret of the user, decrypted.
     */
    public function secret(Model $user): string
    {
        return (string) Fortify::currentEncrypter()->decrypt($user->getAttribute('two_factor_secret'));
    }

    /**
     * The `otpauth://` URL of the secret, as Fortify's trait builds it.
     */
    public function otpauthUrl(Model $user): string
    {
        $name = $this->config->get('app.name');
        $username = $user->getAttribute(Fortify::username());

        return $this->provider->qrCodeUrl(is_string($name) ? $name : 'Panneau', is_string($username) ? $username : '', $this->secret($user));
    }

    /**
     * The QR code of the URL, an SVG document, as Fortify's trait draws it.
     */
    public function qrCodeSvg(Model $user): string
    {
        $svg = (new Writer(new ImageRenderer(
            new RendererStyle(192, 0, null, null, Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(45, 55, 72))),
            new SvgImageBackEnd,
        )))->writeString($this->otpauthUrl($user));

        // Without its XML declaration
        return trim(substr($svg, (int) strpos($svg, "\n") + 1));
    }

    /**
     * @return list<string>
     */
    public function recoveryCodes(Model $user): array
    {
        $codes = json_decode((string) Fortify::currentEncrypter()->decrypt($user->getAttribute('two_factor_recovery_codes')), true);

        return is_array($codes) ? array_values(array_filter($codes, is_string(...))) : [];
    }

    /**
     * Logs in with a recovery code: true when the user has it, which is then replaced by a new
     * one, so that each code works once.
     */
    public function useRecoveryCode(Model $user, string $code): bool
    {
        $found = collect($this->recoveryCodes($user))->first(fn (string $recoveryCode): bool => hash_equals($recoveryCode, $code));
        if ($found === null || ! method_exists($user, 'replaceRecoveryCode')) {
            return false;
        }
        $user->replaceRecoveryCode($found);

        return true;
    }

    /**
     * Whether the API refuses the user until they set it up.
     */
    public function mustSetUp(?Authenticatable $user): bool
    {
        return $this->required($user) && $this->status($user) !== 'enabled';
    }
}
