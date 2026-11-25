<?php

namespace Panneau\Agents\Mcp;

use DateInterval;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Passport\Contracts\AuthorizationViewResponse;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\Passport;
use LogicException;
use Panneau\Http\Middleware\ApplyInterfaceLocale;
use Panneau\Http\Middleware\AuthorizeAgents;

/**
 * OAuth for the MCP server, with laravel/passport and the routes of laravel/mcp (decision 0028).
 * Only loaded with both packages.
 *
 * @internal
 */
final class OAuth
{
    /**
     * Passport's side, once the providers have booted: the scopes of Panneau beside those the
     * project declared, the consent view unless the project set its own, the lifetimes of the
     * config.
     */
    public static function configure(Application $app): void
    {
        Passport::tokensCan([
            ...Passport::$scopes,
            AuthorizeAgents::USE => __('Read your content'),
            AuthorizeAgents::WRITE => __('Read and change your content'),
        ]);
        // Passport binds no view of its own: a binding is the project's
        if (! $app->bound(AuthorizationViewResponse::class)) {
            Passport::authorizationView('panneau::oauth.authorize');
        }

        $config = $app->make(Config::class);
        $access = $config->get('panneau.agents.mcp.oauth.access_token_lifetime');
        if (is_numeric($access)) {
            Passport::tokensExpireIn(new DateInterval('PT'.(int) $access.'M'));
        }
        $refresh = $config->get('panneau.agents.mcp.oauth.refresh_token_lifetime');
        if (is_numeric($refresh)) {
            Passport::refreshTokensExpireIn(new DateInterval('P'.(int) $refresh.'D'));
        }
    }

    /**
     * The pieces of the setup that fail late without a word when they are missing: the guard of
     * the MCP server must be a `passport` guard, its users' model must be Passport's, and
     * `passport.guard` must be the guard the users of Panneau log in on, else Passport's
     * authorization never sees them logged in.
     *
     * @throws LogicException naming the piece that is missing
     */
    public static function check(Config $config): void
    {
        $guard = $config->get('panneau.agents.mcp.guard');
        $guard = is_string($guard) && $guard !== '' ? $guard : 'sanctum';
        $driver = $config->get('auth.guards.'.$guard.'.driver');
        if ($driver !== 'passport') {
            throw new LogicException(sprintf(
                'OAuth for the MCP server of the agents needs a passport guard in panneau.agents.mcp.guard: "%s" %s.',
                $guard,
                is_string($driver) ? sprintf('has the "%s" driver', $driver) : 'is not a guard of config/auth.php',
            ));
        }

        $provider = $config->get('auth.guards.'.$guard.'.provider');
        $model = $config->get('auth.providers.'.(is_string($provider) ? $provider : '').'.model');
        if ($config->get('auth.providers.'.(is_string($provider) ? $provider : '').'.driver') === 'eloquent'
            && is_string($model) && class_exists($model) && ! is_subclass_of($model, OAuthenticatable::class)) {
            throw new LogicException(sprintf(
                'OAuth for the MCP server of the agents needs a user model of laravel/passport: %s must implement %s and use Laravel\\Passport\\HasApiTokens.',
                $model,
                OAuthenticatable::class,
            ));
        }

        $default = $config->get('auth.defaults.guard');
        $panneau = $config->get('panneau.auth.guard') ?? $default;
        $passport = $config->get('passport.guard') ?? $default;
        if ($panneau !== $passport) {
            throw new LogicException(sprintf(
                'passport.guard is "%s", but the users of Panneau log in on "%s": set passport.guard to "%2$s", or Passport\'s authorization never sees them logged in.',
                is_string($passport) ? $passport : '',
                is_string($panneau) ? $panneau : '',
            ));
        }
    }

    /**
     * The routes of laravel/mcp, with Panneau's metadata over its own: same URIs and names, with
     * both scopes.
     */
    public static function routes(Router $router, Application $app): void
    {
        Mcp::oauthRoutes();

        // Guests of Passport's authorization go to the login of Panneau's auth mode, and its
        // consent page is in the language of the interface the user chose. Once the providers
        // have booted too: Passport registers its routes in its own boot, which can come after
        // the project's routes (#305).
        $attach = function () use ($router): void {
            foreach ($router->getRoutes()->getRoutes() as $route) {
                if ($route->getName() === 'passport.authorizations.authorize' && ! in_array(RedirectGuestsToLogin::class, $route->middleware(), true)) {
                    $route->middleware([RedirectGuestsToLogin::class, ApplyInterfaceLocale::class]);
                }
            }
        };
        $attach();
        $app->booted($attach);

        $router->get('.well-known/oauth-protected-resource', [OAuthMetadata::class, 'protectedResource'])
            ->name('mcp.oauth.protected-resource');
        $router->get('.well-known/oauth-protected-resource/{path}', [OAuthMetadata::class, 'protectedResource'])
            ->where('path', '.*')
            ->name('mcp.oauth.protected-resource.nested');
        $router->get('.well-known/oauth-authorization-server', [OAuthMetadata::class, 'authorizationServer'])
            ->name('mcp.oauth.authorization-server');
        $router->get('.well-known/oauth-authorization-server/{path}', [OAuthMetadata::class, 'authorizationServer'])
            ->where('path', '.*')
            ->name('mcp.oauth.authorization-server.nested');
    }
}
