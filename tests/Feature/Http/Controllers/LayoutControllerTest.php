<?php

use Folklore\Panneau\Models\Bubble;

class LayoutControllerTest extends TestCase
{
    public function testDefinition()
    {
        $response = $this->callAsJson('GET', 'panneau/layout/definition');
        if ($response === $this) {
            $response = $this->response;
        }

        $definitionData = json_decode(app('panneau')->layout()->toJson(), true);
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->status());
        $this->assertEquals($definitionData, $responseData);
    }
}
