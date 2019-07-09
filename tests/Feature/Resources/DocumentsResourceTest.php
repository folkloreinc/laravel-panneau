<?php

namespace Panneau\Tests\Feature\Resources;

use Panneau\Tests\TestCase;
use Panneau\Resources\Documents as DocumentsResource;

/**
 */
class DocumentsResourceTest extends TestCase
{
    public function testToArray()
    {
        $resource = new DocumentsResource();
        $data = $resource->toArray();
        $documentTypes = $this->app['panneau']->documents();
        $this->assertEquals('typed', $data['type']);
        $this->assertEquals(
            ['index', 'definition', 'create', 'store', 'show', 'edit', 'update', 'destroy', 'delete'],
            array_keys($data['routes'])
        );
        foreach ($documentTypes as $index => $documentType) {
            $name = $documentType->getName();
            $this->assertArrayHasKey($name, $data['forms']['fields']);
            $this->assertArrayHasKey($name, $data['validation']);
            $this->assertContains([
                'id' => $name,
                'label' => $documentType->getFieldsLabel(),
            ], $data['types']);
        }
    }
}
