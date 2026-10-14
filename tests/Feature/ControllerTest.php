<?php
namespace Panneau\Tests\Feature;

use Panneau\Tests\TestCase;
use Illuminate\Support\Collection;
use Illuminate\Auth\GenericUser;

class ControllerTest extends TestCase
{
    protected $panneau;

    public function setUp(): void
    {
        parent::setUp();

        $this->panneau = $this->app['panneau'];
        $this->panneau->resources([\TestApp\Resources\PagesResource::class]);
    }

    /**
     * Test definition resources
     *
     * @return void
     */
    public function testIndex()
    {
        $response = $this->json('GET', '/panneau/pages');
        // $response->dump();

        $response->assertStatus(200);
    }

    public function testShow()
    {
        $this->actingAs(new GenericUser(['id' => 1]));
        $id = $this->json('GET', '/panneau/pages')->json('data.0.id');

        $this->json('GET', '/panneau/pages/' . $id)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $id);
        $this->json('GET', '/panneau/pages/unknown')->assertStatus(404);
    }

    public function testUpdate()
    {
        $this->actingAs(new GenericUser(['id' => 1]));
        $id = $this->json('GET', '/panneau/pages')->json('data.0.id');

        $this->json('PUT', '/panneau/pages/' . $id, ['title' => 'New title', 'body' => 'Body'])
            ->assertStatus(200)
            ->assertJsonPath('data.title', 'New title');
        $this->json('GET', '/panneau/pages/' . $id)->assertJsonPath('data.title', 'New title');
    }

    public function testDestroy()
    {
        $this->actingAs(new GenericUser(['id' => 1]));
        $id = $this->json('GET', '/panneau/pages')->json('data.0.id');

        $this->json('DELETE', '/panneau/pages/' . $id)
            ->assertStatus(200)
            ->assertJson(['success' => true]);
        $this->json('GET', '/panneau/pages/' . $id)->assertStatus(404);
    }
}
