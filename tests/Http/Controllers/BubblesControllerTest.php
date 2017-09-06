<?php

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Folklore\Panneau\Models\Bubble;

/**
 * @coversDefaultClass Folklore\Panneau\Http\Controllers\BubblesController
 */
class BubblesControllerTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->artisan('migrate', [
            '--database' => 'testing',
            '--path' => realpath(__DIR__.'/../../src/migrations')
        ]);
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

        $response
            ->assertStatus(200)
            ->assertJson([$model->toArray()]);
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

        $response
            ->assertStatus(200)
            ->assertJson($model->toArray());
    }
}
