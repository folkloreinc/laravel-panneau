<?php

namespace Panneau\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Panneau\Auth\EmailVerification;
use Panneau\Auth\TwoFactor;
use Panneau\Definition\DefinitionBuilder;
use Panneau\Exceptions\EmailUnverifiedException;
use Panneau\Exceptions\TwoFactorRequiredException;
use Panneau\Http\Controllers\AppController;
use Symfony\Component\HttpFoundation\Response;

/**
 * The `panneau.auth` middleware: the user must be logged in on the guard of
 * `panneau.auth.guard`, and the `viewPanneau` gate must allow them. Without a `viewPanneau`
 * gate, nobody gets in.
 *
 * Guests of the app's page are redirected to the login URL of the `external` auth mode. In the
 * `panneau` mode, they get the page, without the definition, and the app shows its login page
 * when the API answers them with a `401`.
 *
 * With `panneau.auth.email_verification`, the API refuses users whose address isn't verified
 * with the `urn:panneau:problem:email-unverified` problem: they get the app's page, without the
 * definition, and the app shows its verification page. Users who must have two-factor
 * authentication and haven't set it up get the `urn:panneau:problem:two-factor-required` problem
 * the same way, but on the routes of `/account/two-factor`.
 */
final class Authenticate
{
    public function __construct(
        private readonly Auth $auth,
        private readonly Gate $gate,
        private readonly Config $config,
        private readonly DefinitionBuilder $definition,
        private readonly EmailVerification $verification,
        private readonly TwoFactor $twoFactor,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $guard = $this->config->get('panneau.auth.guard');
        $guard = is_string($guard) ? $guard : null;
        if ($this->auth->guard($guard)->guest()) {
            if ($this->servesGuests($request)) {
                if ($guard !== null) {
                    $this->auth->shouldUse($guard);
                }

                return $next($request);
            }
            throw new AuthenticationException('Unauthenticated.', array_filter([$guard]), $this->definition->auth()['loginUrl']);
        }
        if ($guard !== null) {
            $this->auth->shouldUse($guard);
        }

        $user = $this->auth->guard($guard)->user();
        if ($this->gate->forUser($user)->denies('viewPanneau')) {
            throw new AuthorizationException;
        }
        if ($this->verification->mustVerify($user) && ! $this->servesGuests($request)) {
            throw new EmailUnverifiedException;
        }
        // The routes of the account's two-factor authentication set it up
        if ($this->twoFactor->mustSetUp($user) && ! $this->servesGuests($request) && ! $request->routeIs('panneau.api.account.two-factor*')) {
            throw new TwoFactorRequiredException;
        }

        return $next($request);
    }

    /**
     * Whether a guest, or a user whose address isn't verified, gets the request: the app's page,
     * in the `panneau` mode.
     */
    private function servesGuests(Request $request): bool
    {
        return $this->definition->auth()['mode'] === 'panneau'
            && $request->route()?->getControllerClass() === AppController::class;
    }
}
