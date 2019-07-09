<?php

namespace Panneau\Tests\Feature\Http\Controllers;

use Panneau\Tests\TestCase;
use Panneau\Tests\RunMigrationsTrait;
use Panneau\Models\Document;

/**
 * @coversDefaultClass Panneau\Http\Controllers\ResourceController
 */
class DocumentsControllerTest extends TestCase
{
    use RunMigrationsTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
        $this->withoutAuthentication();
    }

    /**
     * Test listing documents
     *
     * @test
     * @covers ::index
     *
     */
    public function testIndex()
    {
        $model = new Document();
        $model->save();

        $response = $this->callAsJson('GET', '/panneau/documents');
        if ($response === $this) {
            $response = $this->response;
        }

        $modelData = json_decode(Document::find($model->id)->toJson(), true);
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->status());
        $this->assertEquals([$modelData], $responseData);
    }

    /**
     * Test showing a document
     *
     * @test
     * @covers ::show
     *
     */
    public function testShow()
    {
        $model = new Document();
        $model->save();

        $response = $this->callAsJson('GET', '/panneau/documents/'.$model->id);
        if ($response === $this) {
            $response = $this->response;
        }

        $modelData = json_decode(Document::find($model->id)->toJson(), true);
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->status());
        $this->assertEquals($modelData, $responseData);
    }

    /**
     * Test storing a document
     *
     * @test
     * @covers ::store
     *
     */
    public function testStore()
    {
        $modelData = [
            'title' => [
                'en' => 'Test en',
                'fr' => 'Test fr',
            ]
        ];

        $response = $this->callAsJson('POST', '/panneau/documents', [
            'type' => 'page',
            'data' => $modelData
        ]);
        if ($response === $this) {
            $response = $this->response;
        }

        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals(200, $response->status());
        $this->assertEquals($modelData, $responseData['data']);
    }

    /**
     * Test get document's resource definition
     *
     * @test
     * @covers ::definition
     *
     */
    public function testDefinition()
    {
        $response = $this->callAsJson('GET', 'panneau/documents/definition');
        if ($response === $this) {
            $response = $this->response;
        }

        $definitionData = json_decode(app('panneau')->resource('documents')->toJson(), true);
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->status());
        $this->assertEquals($definitionData, $responseData);
    }
}
