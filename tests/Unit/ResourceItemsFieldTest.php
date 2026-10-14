<?php

namespace Panneau\Tests\Unit;

use Panneau\Tests\TestCase;
use TestApp\Fields\PagesItems;
use TestApp\Resources\PagesResource;

class ResourceItemsFieldTest extends TestCase
{
    public function testTypesOfResourceWithTypes()
    {
        $this->app['panneau']->resources([PagesResource::class]);
        $resource = $this->app['panneau']->resource('pages');
        $field = PagesItems::make('pages');

        $this->assertEquals($resource->getTypes()->toArray(), $field->types());
        $this->assertEquals($resource->getTypes()->toArray(), $field->toArray()['types']);
    }
}
