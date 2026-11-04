<?php

namespace Panneau\Auth;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * When the password of the user was last confirmed, in the session (panneau-js decision 0049): a
 * login with the password, or `POST /auth/password/confirm`. It lasts `panneau.auth.password_timeout`
 * seconds (`auth.password_timeout` by default, 3 hours). Kept apart from the site's
 * `auth.password_confirmed_at`, so that a confirmation on one guard doesn't count on the other.
 *
 * @internal
 */
final class PasswordConfirmation
{
    private const KEY = 'panneau.auth.password_confirmed_at';

    public function __construct(private readonly Config $config) {}

    /**
     * Whether the password was confirmed recently. A request without a session, authenticated by
     * a token, has nothing to confirm: it is.
     */
    public function confirmed(Request $request): bool
    {
        if (! $request->hasSession()) {
            return true;
        }
        $at = $request->session()->get(self::KEY);

        return is_int($at) && Carbon::now()->getTimestamp() - $at < $this->timeout();
    }

    public function confirm(Request $request): void
    {
        if ($request->hasSession()) {
            $request->session()->put(self::KEY, Carbon::now()->getTimestamp());
        }
    }

    private function timeout(): int
    {
        $timeout = $this->config->get('panneau.auth.password_timeout') ?? $this->config->get('auth.password_timeout', 10800);

        return is_numeric($timeout) ? (int) $timeout : 10800;
    }
}
