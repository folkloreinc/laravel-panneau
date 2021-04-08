<?php

namespace Panneau\Tests\Feature;

use Panneau\Tests\TestCase;

class DefinitionTest extends TestCase
{
    protected $panneau;

    public function setUp(): void
    {
        parent::setUp();

        $this->panneau = $this->app['panneau'];
    }

    /**
     * A basic test example.
     *
     * @return void
     */
    public function testBasicTest()
    {
        $this->panneau->resources([
            \TestApp\Resources\PagesResource::class
        ]);

        $definition = $this->panneau->definition();

        dd($definition->toArray());
    }
}
