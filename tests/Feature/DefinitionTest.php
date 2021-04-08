<?php

namespace Panneau\Tests\Feature;

use Panneau\Tests\TestCase;
use Illuminate\Support\Collection;

class DefinitionTest extends TestCase
{
    protected $panneau;

    public function setUp(): void
    {
        parent::setUp();

        $this->panneau = $this->app['panneau'];
    }

    /**
     * Definition test resources
     *
     * @return void
     */
    public function testResources()
    {
        $this->panneau->resources([
            \TestApp\Resources\PagesResource::class
        ]);

        $definition = $this->panneau->definition();

        $resources = $definition->resources();

        $this->assertInstanceOf(Collection::class, $resources);
        $this->assertEquals(1, $resources->count());

        $this->panneau->resources([
            \TestApp\Resources\PagesResource::class
        ]);

        $resources = $definition->resources();

        $this->assertInstanceOf(Collection::class, $resources);
        $this->assertEquals(2, $resources->count());
        $this->assertContainsOnly(\TestApp\Resources\PagesResource::class, $resources);
    }
}
