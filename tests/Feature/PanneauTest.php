<?php

namespace Panneau\Tests\Feature;

use Panneau\Tests\TestCase;
use Panneau\Tests\RunMigrationsTrait;
use Panneau\Definition;

/**
 */
class PanneauTest extends TestCase
{
    use RunMigrationsTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $this->runMigrations();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testPanneauDefinition()
    {
        $definition = panneau()->definition();
        $this->assertInstanceOf(Definition::class, $definition);
    }
}
