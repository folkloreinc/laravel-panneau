<?php

namespace Panneau\Tests\Unit;

use Panneau\Tests\TestCase;

class FixturesTest extends TestCase
{
    public function testPageTypesCanBeLoaded()
    {
        $this->assertTrue(class_exists(\TestApp\Resources\Pages\Home::class));
        $this->assertTrue(class_exists(\TestApp\Resources\Pages\Contact::class));
        $this->assertTrue(class_exists(\TestApp\Resources\Pages\StaticPage::class));
    }
}
