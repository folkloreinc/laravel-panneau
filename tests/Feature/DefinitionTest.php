<?php

namespace Panneau\Tests\Feature;

use Panneau\Tests\TestCase;

class DefinitionTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testBasicTest()
    {
        $validDefinition = [];
        $definition = app('panneau')
            ->resources([\PageResource::class])
            ->definition()
            ->toArray();
        dd($definition);
        $this->assertEquals($validDefinition, $definition);
    }
}
