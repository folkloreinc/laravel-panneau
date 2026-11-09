<?php

namespace Panneau\Tests\Unit\Resources;

use Panneau\Resources\ResourceType;
use PHPUnit\Framework\TestCase;

class ResourceTypeTest extends TestCase
{
    public function test_the_id_and_label_come_from_the_class_name(): void
    {
        $this->assertSame('home-page', (new HomePageType)->id());
        $this->assertSame('Home page', (new HomePageType)->label());
        $this->assertSame('event', (new EventResourceType)->id());
        $this->assertSame('landing', (new Landing)->id());
        $this->assertNull((new Landing)->description());
        $this->assertNull((new Landing)->icon());
        $this->assertTrue((new Landing)->canCreate(null));
    }
}

class HomePageType extends ResourceType
{
    public function fields(): array
    {
        return [];
    }
}

class EventResourceType extends ResourceType
{
    public function fields(): array
    {
        return [];
    }
}

class Landing extends ResourceType
{
    public function fields(): array
    {
        return [];
    }
}
