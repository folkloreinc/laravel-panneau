<?php

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Folklore\Panneau\Models\Bubble;
use Folklore\Panneau\Support\FieldsSchema;

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
        $schema = new FieldsSchema();
        $schema->setFields([
            'data' => [
                'type' => 'object',
                'properties' => [
                    'title' => [
                        'type' => 'string',
                    ],
                ],
                'required' => ['title']
            ]
        ]);

        $model = new Bubble();
        $model->setSchema($schema);
        $model->data = [];
        $model->save();
    }
}
