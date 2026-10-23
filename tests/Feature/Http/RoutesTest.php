<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Auth\GenericUser;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Panneau\Http\Problem;
use Panneau\Support\Facade;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\TestCase;
use TestApp\Panneau\Resources\TagsResource;

class RoutesTest extends TestCase
{
    public function test_no_route_is_registered_until_routes_is_called(): void
    {
        $this->assertFalse(Route::has('panneau.api.definition'));
        $this->getJson('/panneau/api/definition')->assertNotFound();
    }

    public function test_routes(): void
    {
        Panneau::routes();
        $this->app['router']->getRoutes()->refreshNameLookups();

        $this->assertSame(url('/panneau/api/definition'), route('panneau.api.definition'));
        $this->assertSame(url('/panneau/api/resources/pages/12'), route('panneau.api.resources.show', ['resource' => 'pages', 'id' => '12']));
        $this->assertSame(url('/panneau/api/resources/pages/actions/publish'), route('panneau.api.resources.actions', ['resource' => 'pages', 'action' => 'publish']));
        $this->assertSame(url('/panneau/api/search'), route('panneau.api.search'));
    }

    public function test_the_search_route_is_rate_limited(): void
    {
        Panneau::routes();
        $this->app['router']->getRoutes()->refreshNameLookups();

        $this->assertContains(
            ThrottleRequests::class.':panneau-search',
            Route::getRoutes()->getByName('panneau.api.search')?->middleware() ?? [],
        );
    }

    public function test_no_search_route_when_search_is_disabled(): void
    {
        $this->app['config']->set('panneau.search.enabled', false);
        Panneau::routes();
        $this->app['router']->getRoutes()->refreshNameLookups();

        $this->assertFalse(Route::has('panneau.api.search'));
    }

    public function test_the_path_and_middleware_come_from_the_config(): void
    {
        $this->app['config']->set('panneau.path', '/admin/');
        $this->app['config']->set('panneau.middleware', ['api']);
        Panneau::routes();
        $this->app['router']->getRoutes()->refreshNameLookups();

        $route = Route::getRoutes()->getByName('panneau.api.definition');

        $this->assertSame('admin/api/definition', $route?->uri());
        $this->assertContains('api', $route?->middleware() ?? []);
        $this->assertNotContains('panneau.auth', $route?->middleware() ?? []);
    }

    public function test_guests_get_a_401_problem(): void
    {
        Panneau::routes();

        $this->getJson('/panneau/api/definition')
            ->assertStatus(401)
            ->assertHeader('Content-Type', Problem::CONTENT_TYPE)
            ->assertExactJson(['type' => 'about:blank', 'title' => 'Unauthorized', 'status' => 401]);
    }

    public function test_guests_get_a_401_problem_without_asking_for_json(): void
    {
        Panneau::routes();

        // The app has no "login" route: a redirect would fail
        $this->get('/panneau/api/definition')
            ->assertStatus(401)
            ->assertHeader('Content-Type', Problem::CONTENT_TYPE);
    }

    public function test_validation_errors_are_problems_without_asking_for_json(): void
    {
        Panneau::routes();
        Gate::define('viewPanneau', fn ($user): bool => true);
        Facade::allowWithoutPolicies();
        Facade::resources([new TagsResource]);
        $this->actingAs(new GenericUser(['id' => 1]));

        $this->post('/panneau/api/resources/tags', [])
            ->assertStatus(422)
            ->assertHeader('Content-Type', Problem::CONTENT_TYPE)
            ->assertJsonStructure(['errors' => ['name']]);
    }

    public function test_users_need_the_view_panneau_gate(): void
    {
        Panneau::routes();
        $this->actingAs(new GenericUser(['id' => 1]));

        // No gate: nobody gets in
        $this->getJson('/panneau/api/definition')->assertStatus(403)->assertHeader('Content-Type', Problem::CONTENT_TYPE);

        Gate::define('viewPanneau', fn ($user): bool => true);
        $this->getJson('/panneau/api/definition')->assertOk();
    }

    public function test_the_guard_comes_from_the_config(): void
    {
        $this->app['config']->set('auth.guards.admin', ['driver' => 'session', 'provider' => 'users']);
        $this->app['config']->set('panneau.auth.guard', 'admin');
        Gate::define('viewPanneau', fn ($user): bool => true);
        Panneau::routes();

        $this->actingAs(new GenericUser(['id' => 1]));
        $this->getJson('/panneau/api/definition')->assertStatus(401);

        $this->actingAs(new GenericUser(['id' => 1]), 'admin');
        $this->getJson('/panneau/api/definition')->assertOk()->assertJsonPath('user.id', '1');
    }

    public function test_other_routes_keep_their_error_format(): void
    {
        Panneau::routes();
        Route::get('/site', fn () => abort(403));

        $response = $this->getJson('/site')->assertStatus(403);

        $this->assertStringStartsWith('application/json', (string) $response->headers->get('Content-Type'));
    }
}
