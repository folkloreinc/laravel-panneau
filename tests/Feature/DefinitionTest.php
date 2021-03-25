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
        dd(app('panneau')->definition()->toArray());
    }
}
