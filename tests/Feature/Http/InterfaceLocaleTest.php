<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Panneau\Support\Facade as Panneau;

/**
 * The language switcher of the interface (panneau-js decision 0083): the languages of
 * `panneau.interface_locales` in the definition, and `GET /locale/{locale}`, which keeps the
 * choice in the session for Panneau's routes.
 */
class InterfaceLocaleTest extends HttpTestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.locale', 'fr');
        $app['config']->set('panneau.interface_locales', ['fr' => 'Français', 'en' => 'English']);
    }

    /**
     * The routes of Panneau again, after a change to the config they depend on.
     */
    private function registerRoutesAgain(): void
    {
        $this->app['router']->setRoutes(new RouteCollection);
        Panneau::routes();
        $this->app['router']->getRoutes()->refreshNameLookups();
    }

    public function test_the_definition_lists_the_languages_with_their_urls(): void
    {
        $this->getJson('/panneau/api/definition')
            ->assertOk()
            ->assertJsonPath('locale', 'fr')
            ->assertJsonPath('locales', ['fr', 'en'])
            ->assertJsonPath('interfaceLocales', [
                ['locale' => 'fr', 'label' => 'Français', 'url' => '/panneau/api/locale/fr'],
                ['locale' => 'en', 'label' => 'English', 'url' => '/panneau/api/locale/en'],
            ]);
    }

    public function test_a_list_of_locales_is_named_in_each_language(): void
    {
        if (! extension_loaded('intl')) {
            $this->markTestSkipped('The names of the languages come from the intl extension.');
        }
        $this->app['config']->set('panneau.interface_locales', ['fr', 'en']);

        $this->getJson('/panneau/api/definition')
            ->assertJsonPath('interfaceLocales.0.label', 'Français')
            ->assertJsonPath('interfaceLocales.1.label', 'English');
    }

    public function test_a_single_language_has_no_switcher(): void
    {
        $this->app['config']->set('panneau.interface_locales', ['fr' => 'Français']);
        $this->registerRoutesAgain();

        $this->getJson('/panneau/api/definition')->assertOk()->assertJsonMissingPath('interfaceLocales');
        $this->get('/panneau/api/locale/fr')->assertNotFound();
    }

    public function test_switching_keeps_the_choice_and_goes_to_next(): void
    {
        $this->get('/panneau/api/locale/en?next=%2Fpanneau%2Fresources%2Fposts%3Fq%3Da')
            ->assertRedirect('/panneau/resources/posts?q=a');
        $this->assertSame('en', session('panneau.locale'));

        $this->getJson('/panneau/api/definition')->assertJsonPath('locale', 'en');
        // The boot config of the page, before the definition
        $this->withoutVite()->get('/panneau/resources/posts')->assertOk()->assertSee('"locale":"en"', false);
    }

    public function test_switching_goes_to_the_app_without_a_local_next(): void
    {
        $this->get('/panneau/api/locale/en')->assertRedirect('/panneau');
        $this->get('/panneau/api/locale/en?next=https%3A%2F%2Fevil.test')->assertRedirect('/panneau');
        $this->get('/panneau/api/locale/en?next=%2F%2Fevil.test%2Fpanneau')->assertRedirect('/panneau');
        $this->get('/panneau/api/locale/en?next=%2Fadmin')->assertRedirect('/panneau');
    }

    public function test_a_language_that_is_not_offered_is_not_found(): void
    {
        $this->getJson('/panneau/api/locale/de')->assertNotFound();
        $this->assertNull(session('panneau.locale'));
    }

    public function test_a_choice_that_is_not_offered_anymore_is_ignored(): void
    {
        $this->withSession(['panneau.locale' => 'de'])
            ->getJson('/panneau/api/definition')
            ->assertJsonPath('locale', 'fr');
    }

    public function test_guests_cannot_switch(): void
    {
        $this->app['auth']->guard()->forgetUser();

        $this->getJson('/panneau/api/locale/en')->assertUnauthorized();
        $this->assertNull(session('panneau.locale'));
    }

    public function test_a_route_of_the_project_applies_the_choice_with_the_alias(): void
    {
        Route::get('/exports/own', fn () => app()->getLocale())
            ->middleware([...config('panneau.middleware'), 'panneau.interface-locale']);
        Route::get('/exports/without', fn () => app()->getLocale())->middleware(config('panneau.middleware'));
        $this->app['router']->getRoutes()->refreshNameLookups();

        $this->get('/exports/own')->assertOk()->assertContent('fr');
        $this->withSession(['panneau.locale' => 'en'])->get('/exports/own')->assertContent('en');
        // The test reuses the application: the request before left its locale
        $this->app->setLocale('fr');
        $this->withSession(['panneau.locale' => 'en'])->get('/exports/without')->assertContent('fr');
        $this->app->setLocale('fr');
        $this->withSession(['panneau.locale' => 'de'])->get('/exports/own')->assertContent('fr');
    }
}
