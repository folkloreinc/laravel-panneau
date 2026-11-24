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
 * it redirects to the intended URL; the app's login page, in the `panneau` mode, doesn't yet: the
 * user opens the authorization again. With `prompt=none`, or without a login, Passport answers.
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
            ? ($this->router->has('panneau.app') ? $this->url->route('panneau.app') : null)
            : $auth['loginUrl'];

        return is_string($login) && $login !== '' ? $this->redirect->guest($login) : $next($request);
    }
}
