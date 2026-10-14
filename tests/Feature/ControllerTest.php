<?php

namespace Panneau\Tests\Feature;

use Panneau\Tests\TestCase;
use TestApp\Resources\PagesResource;

class ControllerTest extends TestCase
{
    protected $panneau;

    protected function setUp(): void
    {
        parent::setUp();

        $this->panneau = $this->app['panneau'];
        $this->panneau->resources([PagesResource::class]);
    }

    /**
     * Test definition resources
     *
     * @return void
     */
    public function test_index()
    {
        $response = $this->json('GET', '/panneau/pages');
        // $response->dump();

        $response->assertStatus(200);
    }
}
