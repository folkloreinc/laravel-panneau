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
}
