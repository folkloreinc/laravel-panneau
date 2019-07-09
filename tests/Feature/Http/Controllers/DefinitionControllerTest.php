<?php

namespace Panneau\Tests\Feature\Http\Controllers;

use Panneau\Tests\TestCase;
use Panneau\Models\Bubble;

class DefinitionControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutAuthentication();
    }

    public function testLayout()
    {
        $response = $this->callAsJson('GET', 'panneau/definition/layout');
        if ($response === $this) {
            $response = $this->response;
        }
        $responseData = json_decode($response->getContent(), true);
        $definitionData = json_decode(app('panneau')->layout()->toJson(), true);

        $this->assertEquals(200, $response->status());
        $this->assertEquals($definitionData, $responseData);
    }

    public function testBlocks()
    {
        $response = $this->callAsJson('GET', 'panneau/definition/blocks');
        if ($response === $this) {
            $response = $this->response;
        }
        $responseData = json_decode($response->getContent(), true);

        $blocks = app('panneau')->blocks();
        $definitions = [];
        foreach ($blocks as $block) {
            $definitions[$block->getName()] = $block->toArray();
        }
        $definitionData = json_decode(json_encode($definitions), true);

        $this->assertEquals(200, $response->status());
        $this->assertEquals($definitionData, $responseData);
    }

    public function testBlocksFields()
    {
        $response = $this->callAsJson('GET', 'panneau/definition/blocks?fields=true');
        if ($response === $this) {
            $response = $this->response;
        }
        $responseData = json_decode($response->getContent(), true);

        $blocks = app('panneau')->blocks();
        $definitions = [];
        foreach ($blocks as $block) {
            $definitions[$block->getName()] = $block->toFieldsArray();
        }
        $definitionData = json_decode(json_encode($definitions), true);

        $this->assertEquals(200, $response->status());
        $this->assertEquals($definitionData, $responseData);
    }

    public function testDocuments()
    {
        $response = $this->callAsJson('GET', 'panneau/definition/documents');
        if ($response === $this) {
            $response = $this->response;
        }
        $responseData = json_decode($response->getContent(), true);

        $documents = app('panneau')->documents();
        $definitions = [];
        foreach ($documents as $document) {
            $definitions[$document->getName()] = $document->toArray();
        }
        $definitionData = json_decode(json_encode($definitions), true);

        $this->assertEquals(200, $response->status());
        $this->assertEquals($definitionData, $responseData);
    }

    public function testDocumentsFields()
    {
        $response = $this->callAsJson('GET', 'panneau/definition/documents?fields=true');
        if ($response === $this) {
            $response = $this->response;
        }
        $responseData = json_decode($response->getContent(), true);

        $documents = app('panneau')->documents();
        $definitions = [];
        foreach ($documents as $document) {
            $definitions[$document->getName()] = $document->toFieldsArray();
        }
        $definitionData = json_decode(json_encode($definitions), true);

        $this->assertEquals(200, $response->status());
        $this->assertEquals($definitionData, $responseData);
    }
}
