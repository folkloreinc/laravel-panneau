<?php

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Folklore\Panneau\Models\Bubble;

/**
 * @coversDefaultClass Folklore\Panneau\Http\Controllers\BubblesController
 */
class BubblesControllerTest extends TestCase
{
    use RunMigrationsTrait, WithoutMiddleware;

    public function setUp()
    {
        parent::setUp();
        $this->runMigrations();
    }

    protected function callAsJson($method, $uri, $data = [])
    {
        $content = json_encode($data);
        dump('callAsJson', $content);
        return $this->call($method, $uri, [], [], [], [
            'CONTENT_TYPE' => 'application',
            'HTTP_Accept' => 'application/json',
        ], $content);
    }

    /**
     * Test listing bubbles
     *
     * @test
     * @covers ::index
     *
     */
    public function testIndex()
    {
        $this->withMiddleware();

        $model = new Bubble();
        $model->save();

        $response = $this->callAsJson('GET', '/panneau/bubbles');
        if ($response === $this) {
            $response = $this->response;
        }

        $modelData = json_decode(Bubble::find($model->id)->toJson(), true);
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->status());
        $this->assertEquals([$modelData], $responseData);
    }

    /**
     * Test showing a bubble
     *
     * @test
     * @covers ::show
     *
     */
    public function testShow()
    {
        $this->withMiddleware();

        $model = new Bubble();
        $model->save();

        $response = $this->callAsJson('GET', '/panneau/bubbles/'.$model->id);
        if ($response === $this) {
            $response = $this->response;
        }

        $modelData = json_decode(Bubble::find($model->id)->toJson(), true);
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->status());
        $this->assertEquals($modelData, $responseData);
    }

    /**
     * Test storing a bubble
     *
     * @test
     * @covers ::store
     *
     */
    public function testStore()
    {
        $this->withMiddleware();

        $modelData = [
            'data' => [
                'title' => [
                    'en' => 'Test en',
                    'fr' => 'Test fr',
                ]
            ]
        ];

        $response = $this->callAsJson('POST', '/panneau/bubbles', $modelData);
        if ($response === $this) {
            $response = $this->response;
        }

        $responseData = json_decode($response->getContent(), true);
        // dd($responseData);
        $this->assertEquals(200, $response->status());
        // $this->assertEquals($modelData, $responseData);
    }

    /**
     * Test get bubble's resource definition
     *
     * @test
     * @covers ::definition
     *
     */
    public function testDefinition()
    {
        $this->withMiddleware();

        $response = $this->callAsJson('GET', 'panneau/bubbles/definition');
        if ($response === $this) {
            $response = $this->response;
        }

        $definitionData = json_decode(app('panneau')->resource('bubbles')->toJson(), true);
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->status());
        $this->assertEquals($definitionData, $responseData);
    }
}
