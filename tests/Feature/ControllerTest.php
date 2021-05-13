<?php
namespace Panneau\Tests\Feature;

use Panneau\Tests\TestCase;
use Illuminate\Support\Collection;

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
}
