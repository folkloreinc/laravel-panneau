<?php

namespace Panneau\Tests\Unit;

use Panneau\Tests\TestCase;
use TestApp\Http\Resources\LandingPageResource;
use TestApp\Http\Resources\PageResource;
use TestApp\Items\Page;
use TestApp\Items\TypedPage;
use TestApp\Resources\TypedPagesResource;

class TypedJsonResourceTest extends TestCase
{
    protected function makeJsonResource($item)
    {
        return $this->app->make(TypedPagesResource::class)->makeJsonResource($item);
    }

    public function testTypeWithJsonResource()
    {
        $resource = $this->makeJsonResource(new TypedPage(['id' => '1', 'type' => 'landing']));

        $this->assertInstanceOf(LandingPageResource::class, $resource);
        $this->assertSame('landing', $resource->toArray(request())['type']);
    }

    public function testTypeWithoutJsonResource()
    {
        $resource = $this->makeJsonResource(new TypedPage(['id' => '1', 'type' => 'home']));

        $this->assertInstanceOf(PageResource::class, $resource);
    }

    public function testUnknownType()
    {
        $resource = $this->makeJsonResource(new TypedPage(['id' => '1', 'type' => 'unknown']));

        $this->assertInstanceOf(PageResource::class, $resource);
    }

    public function testItemWithoutType()
    {
        $resource = $this->makeJsonResource(new Page(['id' => '1']));

        $this->assertInstanceOf(PageResource::class, $resource);
    }
}
