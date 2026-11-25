<?php

namespace Panneau\Agents\Mcp;

use Closure;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\Router;
use Illuminate\Routing\UrlGenerator;
use Panneau\Definition\DefinitionBuilder;
use Symfony\Component\HttpFoundation\Response;

/**
 * On Passport's authorization: a guest goes to the login of Panneau's auth mode, and the session
 * keeps the authorization as the intended URL. The `external` mode's login brings them back if
 * it redirects to the intended URL, or follows the query parameter of
 * `panneau.auth.login_redirect_parameter`, the same relative path as `next`. In the `panneau` mode, the app's page gets a `next` query
 * parameter, the path and query of the authorization (relative, same origin), and the app goes
 * there with a full page load once logged in (decision 0030). With `prompt=none`, or without a
 * login, Passport answers.
 *
 * @internal
 */
final class RedirectGuestsToLogin
{
    public function __construct(
        private readonly Auth $auth,
        private readonly Config $config,
        private readonly DefinitionBuilder $definition,
        private readonly Router $router,
        private readonly UrlGenerator $url,
        private readonly Redirector $redirect,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $guard = $this->config->get('passport.guard');
        if (! $this->auth->guard(is_string($guard) ? $guard : null)->guest() || str_contains($request->string('prompt')->toString(), 'none')) {
            return $next($request);
        }
        $auth = $this->definition->auth();
        $login = $auth['mode'] === 'panneau'
            ? ($this->router->has('panneau.app') ? $this->url->route('panneau.app', ['next' => $request->getRequestUri()]) : null)
            : $this->externalLogin($auth['loginUrl'], $request);

        return is_string($login) && $login !== '' ? $this->redirect->guest($login) : $next($request);
    }

    /**
     * The login URL of the `external` mode, with the authorization in its
     * `panneau.auth.login_redirect_parameter` when the config names one (#305): a login that
     * answers in JSON, or a SPA, doesn't follow the intended URL of the session.
     */
    private function externalLogin(?string $login, Request $request): ?string
    {
        $parameter = $this->config->get('panneau.auth.login_redirect_parameter');
        if (! is_string($login) || $login === '' || ! is_string($parameter) || $parameter === '') {
            return $login;
        }

        return $login.(str_contains($login, '?') ? '&' : '?').rawurlencode($parameter).'='.rawurlencode($request->getRequestUri());
    }
}
