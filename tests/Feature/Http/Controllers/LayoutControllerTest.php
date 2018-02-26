<?php

use Folklore\Panneau\Models\Bubble;

class LayoutControllerTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->withoutAuthentication();
    }

    public function testDefinition()
    {
        $response = $this->callAsJson('GET', 'panneau/definition/layout');
        if ($response === $this) {
            $response = $this->response;
        }

        $definitionData = json_decode(app('panneau')->getDefinitionLayout()->toJson(), true);
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->status());
        $this->assertEquals($definitionData, $responseData);
    }
}
