<?php

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Folklore\Panneau\Models\Bubble;

class BubbleTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->artisan('migrate', ['--database' => 'testing']);
    }

    /**
     * Test with invalid schema
     *
     * @expectedException \Folklore\Panneau\Exceptions\SchemaValidationException
     *
     */
    public function testInvalidDataSchema()
    {
        $model = new Bubble();
        $model->data = [];
        $model->save();
    }
}
