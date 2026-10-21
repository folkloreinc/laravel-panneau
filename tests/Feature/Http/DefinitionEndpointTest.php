<?php

namespace Panneau\Tests\Feature\Http;

use Panneau\Tests\Concerns\ValidatesProtocol;

class DefinitionEndpointTest extends HttpTestCase
{
    use ValidatesProtocol;

    public function test_the_definition(): void
    {
        $response = $this->getJson(self::API.'/definition')->assertOk();

        $this->assertValidDefinition($response->json());
        $response
            ->assertJsonPath('user', ['id' => '1', 'name' => 'Ada', 'email' => 'ada@example.com', 'abilities' => ['panneau.view' => true]])
            ->assertJsonPath('resources.0.id', 'posts')
            ->assertJsonPath('resources.0.abilities', ['viewAny' => true, 'create' => true])
            ->assertJsonPath('resources.2.abilities', ['viewAny' => false, 'create' => false]);
    }

    public function test_it_is_translated_in_the_current_locale(): void
    {
        $this->app->setLocale('fr');

        $this->getJson(self::API.'/definition')->assertJsonPath('locale', 'fr');
    }

    public function test_the_theme(): void
    {
        $this->app['config']->set('panneau.theme', [
            'extends' => 'brand',
            'colorScheme' => 'auto',
            'tokens' => ['panneau-color-accent' => '#0055ff'],
            'tones' => ['brand' => '#0055ff'],
        ]);

        $response = $this->getJson(self::API.'/definition')->assertOk();

        $this->assertValidDefinition($response->json());
        $response->assertJsonPath('theme', [
            'extends' => 'brand',
            'colorScheme' => 'auto',
            'tokens' => ['panneau-color-accent' => '#0055ff'],
            'tones' => ['brand' => '#0055ff'],
        ]);
    }

    public function test_no_theme_by_default(): void
    {
        $this->getJson(self::API.'/definition')->assertOk()->assertJsonMissingPath('theme');
    }

    public function test_no_uploads_by_default(): void
    {
        $this->getJson(self::API.'/definition')->assertOk()->assertJsonMissingPath('uploads');
        $this->postJson(self::API.'/uploads/sign', ['method' => 'POST', 'key' => 'video.mp4'])->assertNotFound();
    }
}
