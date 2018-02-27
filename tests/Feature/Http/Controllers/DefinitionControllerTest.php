<?php

use Folklore\Panneau\Models\Bubble;

class DefinitionControllerTest extends TestCase
{
    public function setUp()
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
        $definitionData = json_decode(app('panneau')->getDefinitionLayout()->toJson(), true);

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

        $blocks = app('panneau')->getBlocks();
        $definitions = [];
        foreach ($blocks as $block) {
            $definitions[] = $block->toArray();
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

        $blocks = app('panneau')->getBlocks();
        $definitions = [];
        foreach ($blocks as $block) {
            $definitions[] = $block->toFieldsArray();
        }
        $definitionData = json_decode(json_encode($definitions), true);

        $this->assertEquals(200, $response->status());
        $this->assertEquals($definitionData, $responseData);
    }

    public function testPages()
    {
        $response = $this->callAsJson('GET', 'panneau/definition/pages');
        if ($response === $this) {
            $response = $this->response;
        }
        $responseData = json_decode($response->getContent(), true);

        $pages = app('panneau')->getPages();
        $definitions = [];
        foreach ($pages as $page) {
            $definitions[] = $page->toArray();
        }
        $definitionData = json_decode(json_encode($definitions), true);

        $this->assertEquals(200, $response->status());
        $this->assertEquals($definitionData, $responseData);
    }

    public function testPagesFields()
    {
        $response = $this->callAsJson('GET', 'panneau/definition/pages?fields=true');
        if ($response === $this) {
            $response = $this->response;
        }
        $responseData = json_decode($response->getContent(), true);

        $pages = app('panneau')->getPages();
        $definitions = [];
        foreach ($pages as $page) {
            $definitions[] = $page->toFieldsArray();
        }
        $definitionData = json_decode(json_encode($definitions), true);

        $this->assertEquals(200, $response->status());
        $this->assertEquals($definitionData, $responseData);
    }
}
