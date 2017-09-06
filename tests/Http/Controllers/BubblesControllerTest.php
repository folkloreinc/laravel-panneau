<?php

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Folklore\Panneau\Models\Bubble;

/**
 * @coversDefaultClass Folklore\Panneau\Http\Controllers\BubblesController
 */
class BubblesControllerTest extends TestCase
{
    use RunMigrationsTrait;

    public function setUp()
    {
        parent::setUp();
        $this->runMigrations();
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
        $model = new Bubble();
        $model->save();

        $response = $this->json('GET', '/panneau/bubbles');

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
        $model = new Bubble();
        $model->save();

        $response = $this->json('GET', '/panneau/bubbles/'.$model->id);

        $modelData = json_decode(Bubble::find($model->id)->toJson(), true);
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->status());
        $this->assertEquals($modelData, $responseData);
    }
}
