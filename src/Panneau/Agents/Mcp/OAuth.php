<?php

namespace Panneau\Agents\Mcp;

use DateInterval;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Passport\Contracts\AuthorizationViewResponse;
use Laravel\Passport\Passport;
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
     * The routes of laravel/mcp, with Panneau's metadata over its own: same URIs and names, with
     * both scopes.
     */
    public static function routes(Router $router): void
    {
        Mcp::oauthRoutes();

        // Guests of Passport's authorization go to the login of Panneau's auth mode
        foreach ($router->getRoutes()->getRoutes() as $route) {
            if ($route->getName() === 'passport.authorizations.authorize') {
                $route->middleware(RedirectGuestsToLogin::class);
            }
        }

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
