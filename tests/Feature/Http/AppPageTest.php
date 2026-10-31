<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Auth\GenericUser;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\Testing\TestResponse;
use Panneau\Http\Problem;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\Concerns\ValidatesProtocol;
use Symfony\Component\HttpFoundation\Response;

class AppPageTest extends HttpTestCase
{
    use ValidatesProtocol;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * The boot config written in a page.
     *
     * @param  TestResponse<Response>  $response
     * @return array<string, mixed>
     */
    private function bootConfig(TestResponse $response): array
    {
        $html = (string) $response->getContent();
        $this->assertSame(1, preg_match('#<script type="application/json" id="panneau-boot">(.*?)</script>#s', $html, $matches), 'The page has no boot config.');
        $boot = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($boot);

        return $boot;
    }

    public function test_the_page_boots_the_app(): void
    {
        $response = $this->get('/panneau')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSee('<html lang="en">', false)
            ->assertSee('<title>Laravel</title>', false)
            ->assertSee('<div id="panneau"></div>', false);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $boot = $this->bootConfig($response);
        $this->assertValidBootConfig($boot);
        $this->assertSame(1, $boot['protocol']);
        $this->assertSame('/panneau/api', $boot['apiBaseUrl']);
        $this->assertSame('/panneau', $boot['basePath']);
        $this->assertSame('en', $boot['locale']);
        $this->assertSame('/login', $boot['loginUrl']);
        $this->assertSame(['mode' => 'external', 'loginUrl' => '/login', 'logoutUrl' => null], $boot['auth']);
        $this->assertEquals(json_decode((string) json_encode(Panneau::definition(auth()->user())), true), $boot['definition']);
        $this->assertSame(['posts', 'authors', 'tags'], array_column($boot['definition']['resources'], 'id'));
    }

    public function test_the_page_is_served_for_every_path_of_the_app(): void
    {
        foreach (['/panneau/', '/panneau/resources/posts', '/panneau/resources/posts/12/edit', '/panneau/pages/home', '/panneau/unknown/path', '/panneau/apis'] as $path) {
            $this->get($path)->assertOk()->assertSee('id="panneau-boot"', false);
        }
    }

    public function test_the_api_is_not_served_by_the_page(): void
    {
        $this->getJson('/panneau/api/definition')->assertOk()->assertJsonPath('protocol', 1);

        foreach (['/panneau/api', '/panneau/api/', '/panneau/api/unknown'] as $path) {
            $this->get($path)->assertNotFound()->assertDontSee('panneau-boot');
        }
    }

    public function test_the_route_of_the_page(): void
    {
        $this->assertSame(url('/panneau'), route('panneau.app'));
        $this->assertSame(url('/panneau/resources/posts'), route('panneau.app', ['path' => 'resources/posts']));
    }

    public function test_routes_of_the_project_registered_before_are_kept(): void
    {
        $this->app['router']->setRoutes(new RouteCollection);
        Route::middleware('web')->get('/panneau/custom', fn (): string => 'custom');
        Panneau::routes();

        $this->get('/panneau/custom')->assertOk()->assertContent('custom');
    }

    public function test_the_definition_can_be_left_out_of_the_page(): void
    {
        $this->app['config']->set('panneau.app.inline_definition', false);

        $boot = $this->bootConfig($this->get('/panneau')->assertOk());

        $this->assertValidBootConfig($boot);
        $this->assertArrayNotHasKey('definition', $boot);
    }

    public function test_the_theme_is_in_the_definition_of_the_page(): void
    {
        $theme = [
            'extends' => 'brand',
            'tokens' => ['panneau-font-family-body' => '"Geist", sans-serif'],
            'fonts' => [['family' => 'Geist', 'src' => "url(/fonts/geist.woff2) format('woff2')"]],
        ];
        $this->app['config']->set('panneau.theme', $theme);

        $boot = $this->bootConfig($this->get('/panneau')->assertOk());

        $this->assertValidBootConfig($boot);
        $this->assertSame($theme, $boot['definition']['theme']);
    }

    public function test_the_locale_is_the_app_locale(): void
    {
        $this->app->setLocale('fr_CA');

        $response = $this->get('/panneau')->assertOk()->assertSee('<html lang="fr-CA">', false);

        $this->assertSame('fr_CA', $this->bootConfig($response)['locale']);
    }

    public function test_the_boot_config_is_escaped(): void
    {
        $this->app['config']->set('panneau.name', '</script><script>alert(1)</script> & co');

        $response = $this->get('/panneau')->assertOk()->assertDontSee('<script>alert(1)', false);

        $this->assertSame('</script><script>alert(1)</script> & co', $this->bootConfig($response)['definition']['name']);
    }

    public function test_the_path_and_the_api_url_come_from_the_config(): void
    {
        $this->app['router']->setRoutes(new RouteCollection);
        $this->app['config']->set('panneau.path', '/');
        $this->app['config']->set('panneau.domain', 'admin.example.com');
        $this->app['config']->set('panneau.api.base_url', 'https://admin.example.com/api');
        Panneau::routes();

        $boot = $this->bootConfig($this->get('http://admin.example.com/resources/posts')->assertOk());
        $this->assertValidBootConfig($boot);
        $this->assertSame('/', $boot['basePath']);
        $this->assertSame('https://admin.example.com/api', $boot['apiBaseUrl']);

        $this->getJson('http://admin.example.com/api/definition')->assertOk();
        $this->get('http://example.com/resources/posts')->assertNotFound();
    }

    public function test_guests_are_redirected_to_the_login_url(): void
    {
        $this->app['config']->set('panneau.auth.login_url', '/admin/login');
        $this->app['auth']->guard()->forgetUser();

        $this->get('/panneau/resources/posts?page=2')->assertRedirect('/admin/login');
        $this->assertSame(url('/panneau/resources/posts?page=2'), session('url.intended'));

        // The API still answers with a problem
        $this->get('/panneau/api/definition')->assertStatus(401)->assertHeader('Content-Type', Problem::CONTENT_TYPE);
    }

    public function test_the_logout_url_is_in_the_boot_config(): void
    {
        $this->app['config']->set('panneau.auth.logout_url', '/logout');

        $boot = $this->bootConfig($this->get('/panneau')->assertOk());

        $this->assertValidBootConfig($boot);
        $this->assertSame('/logout', $boot['auth']['logoutUrl']);
        $this->assertSame('/logout', $boot['definition']['auth']['logoutUrl']);
    }

    public function test_guests_get_the_page_in_the_panneau_mode(): void
    {
        $this->app['config']->set('panneau.auth.mode', 'panneau');
        $this->app['auth']->guard()->forgetUser();

        $response = $this->get('/panneau/resources/posts?page=2')->assertOk()->assertSee('<div id="panneau"></div>', false);

        $boot = $this->bootConfig($response);
        $this->assertValidBootConfig($boot);
        $this->assertSame(['mode' => 'panneau', 'loginUrl' => null, 'logoutUrl' => null], $boot['auth']);
        $this->assertArrayNotHasKey('loginUrl', $boot);
        // The definition is the user's: the app fetches it after the login
        $this->assertArrayNotHasKey('definition', $boot);
        $this->assertNull(session('url.intended'));

        // The API still answers with a problem, which shows the login page
        $this->get('/panneau/api/definition')->assertStatus(401)->assertHeader('Content-Type', Problem::CONTENT_TYPE);
    }

    public function test_guests_of_a_separate_guard_get_the_page_in_the_panneau_mode(): void
    {
        $this->app['config']->set('auth.guards.admin', ['driver' => 'session', 'provider' => 'users']);
        $this->app['config']->set('panneau.auth.guard', 'admin');
        $this->app['config']->set('panneau.auth.mode', 'panneau');

        // Ada is logged in on the site's guard, not on the admin's
        $boot = $this->bootConfig($this->get('/panneau')->assertOk());

        $this->assertValidBootConfig($boot);
        $this->assertArrayNotHasKey('definition', $boot);
    }

    public function test_users_get_the_definition_in_the_panneau_mode(): void
    {
        $this->app['config']->set('panneau.auth.mode', 'panneau');

        $boot = $this->bootConfig($this->get('/panneau')->assertOk());

        $this->assertValidBootConfig($boot);
        $this->assertSame(['mode' => 'panneau', 'loginUrl' => null, 'logoutUrl' => null], $boot['auth']);
        $this->assertSame('1', $boot['definition']['user']['id']);
    }

    public function test_users_need_the_view_panneau_gate_in_the_panneau_mode(): void
    {
        $this->app['config']->set('panneau.auth.mode', 'panneau');
        $this->actingAs(new GenericUser(['id' => 99, 'name' => 'Eve']));

        $this->get('/panneau')->assertForbidden()->assertDontSee('panneau-boot');
    }

    public function test_users_need_the_view_panneau_gate(): void
    {
        $this->actingAs(new GenericUser(['id' => 99, 'name' => 'Eve']));

        $response = $this->get('/panneau')->assertForbidden();

        $this->assertNotSame(Problem::CONTENT_TYPE, $response->headers->get('Content-Type'));
    }

    public function test_the_page_loads_the_vite_entry(): void
    {
        $hot = sys_get_temp_dir().'/panneau-hot-'.bin2hex(random_bytes(4));
        file_put_contents($hot, 'http://localhost:5173');
        $this->withVite();
        Vite::useHotFile($hot);

        try {
            $this->get('/panneau')
                ->assertOk()
                ->assertSee('<script type="module" src="http://localhost:5173/@vite/client"></script>', false)
                ->assertSee('<script type="module" src="http://localhost:5173/resources/js/panneau.tsx"></script>', false)
                ->assertSee('window.$RefreshReg$', false);

            $this->app['config']->set('panneau.app.entry', 'resources/admin/main.tsx');
            $this->get('/panneau')->assertSee('src="http://localhost:5173/resources/admin/main.tsx"', false);
        } finally {
            (new Filesystem)->delete($hot);
        }
    }

    public function test_the_page_has_the_csp_nonce_of_vite(): void
    {
        $this->get('/panneau')->assertOk()->assertDontSee('csp-nonce', false);

        Vite::useCspNonce('a1b2c3');

        $this->get('/panneau')->assertOk()->assertSee('<meta property="csp-nonce" content="a1b2c3">', false);
    }

    public function test_the_view_can_be_replaced(): void
    {
        $views = sys_get_temp_dir().'/panneau-views-'.bin2hex(random_bytes(4));
        (new Filesystem)->ensureDirectoryExists($views);
        file_put_contents($views.'/admin.blade.php', '<main data-title="{{ $title }}" data-entry="{{ $entry }}">@json($boot[\'basePath\'])</main>');
        $this->app['view']->addLocation($views);
        $this->app['config']->set('panneau.app.view', 'admin');

        try {
            $this->get('/panneau')->assertOk()->assertSee('<main data-title="Laravel" data-entry="resources/js/panneau.tsx">"\/panneau"</main>', false);
        } finally {
            (new Filesystem)->deleteDirectory($views);
        }
    }
}
