<?php

namespace Panneau\Tests\Feature\Agents;

use Laravel\Passport\PassportServiceProvider;
use Orchestra\Testbench\Attributes\WithMigration;
use Panneau\Agents\Mcp\RedirectGuestsToLogin;
use TestApp\Providers\McpRoutesProvider;

/**
 * The project's routes load before Passport registers its own (#305): the login of Panneau still
 * gets Passport's authorization.
 */
#[WithMigration]
class OAuthProviderOrderTest extends OAuthTestCase
{
    protected function getPackageProviders($app): array
    {
        $providers = array_values(array_diff(parent::getPackageProviders($app), [PassportServiceProvider::class]));

        return [...$providers, McpRoutesProvider::class, PassportServiceProvider::class];
    }

    protected bool $registersMcpRoutes = false;

    public function test_a_guest_of_the_authorization_goes_to_the_login(): void
    {
        $route = $this->app['router']->getRoutes()->getByName('passport.authorizations.authorize');
        $this->assertNotNull($route);
        $this->assertSame(1, count(array_keys($route->middleware(), RedirectGuestsToLogin::class, true)));

        $this->get('/oauth/authorize?'.http_build_query($this->authorization($this->registerClient(), 'mcp:use', str_repeat('a', 64))))
            ->assertRedirect('/login');
    }
}
