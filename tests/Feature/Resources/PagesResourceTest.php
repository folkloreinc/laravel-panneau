<?php

use Folklore\Panneau\Resources\Pages as PagesResource;

/**
 */
class PagesResourceTest extends TestCase
{
    public function testToArray()
    {
        $resource = new PagesResource();
        $data = $resource->toArray();
        $pageTypes = panneau()->getPages();
        $this->assertEquals('typed', $data['type']);
        $this->assertEquals(
            ['index', 'definition', 'create', 'store', 'show', 'edit', 'update', 'destroy', 'delete'],
            array_keys($data['routes'])
        );
        foreach ($pageTypes as $index => $pageType) {
            $name = $pageType->getName();
            $this->assertArrayHasKey($name, $data['forms']['fields']);
            $this->assertArrayHasKey($name, $data['validation']);
            $this->assertEquals([
                'id' => $name,
                'label' => $pageType->label,
            ], $data['types'][$index]);
        }
    }
}
