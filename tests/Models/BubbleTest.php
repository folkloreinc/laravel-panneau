<?php

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Folklore\Panneau\Models\Bubble;
use Folklore\Panneau\Models\Block;
use Folklore\Panneau\Support\FieldsSchema;

class BubbleTest extends TestCase
{
    use RunMigrationsTrait;

    protected $schema;

    protected $relationsSchema;

    public function setUp()
    {
        parent::setUp();

        $this->runMigrations();

        $this->schema = new FieldsSchema([
            'fields' => [
                'data' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => \Folklore\Panneau\Schemas\Fields\TextLocale::class,
                    ],
                    'required' => ['title']
                ]
            ]
        ]);

        $this->relationsSchema = new FieldsSchema([
            'fields' => [
                'data' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => \Folklore\Panneau\Schemas\Fields\TextLocale::class,
                        'bubbles' => \Folklore\Panneau\Schemas\Fields\Bubbles::class,
                    ],
                    'required' => ['title']
                ]
            ]
        ]);
    }

    public function tearDown()
    {
        parent::tearDown();

        Bubble::setDefaultSchema(null);
    }

    /**
     * Test with invalid data
     *
     * @expectedException \Folklore\Panneau\Exceptions\SchemaValidationException
     * @covers \Folklore\Panneau\Support\Traits\HasFieldsSchema::setSchema
     */
    public function testInvalidData()
    {
        $model = new Bubble();
        $model->setSchema($this->schema);
        $model->data = [];
        $model->save();
    }

    /**
     * Test with valid data
     *
     * @covers \Folklore\Panneau\Support\Traits\HasFieldsSchema::setSchema
     */
    public function testValidData()
    {
        $data = json_decode(json_encode([
            'title' => [
                'en' => 'Test'
            ]
        ]));

        $model = new Bubble();
        $model->setSchema($this->schema);
        $model->data = $data;
        $model->save();

        $model = Bubble::find($model->id);
        $this->assertEquals($data, $model->data);
    }

    /**
     * Test with valid data
     *
     * @covers \Folklore\Panneau\Support\Traits\HasFieldsSchema::setSchema
     */
    public function testBubblesRelations()
    {
        Bubble::setDefaultSchema($this->relationsSchema);

        $relation = new Bubble();
        $relation->save();

        $modelData = json_decode(json_encode([
            'title' => [
                'en' => 'Test'
            ],
            'bubbles' => [
                $relation
            ]
        ]));
        $model = new Bubble();
        $model->data = $modelData;
        $model->save();

        $model = Bubble::with('bubbles')->find($model->id);
        $this->assertEquals(1, sizeof($model->bubbles));
        $this->assertEquals('data.bubbles.0', $model->bubbles[0]->pivot->handle);
        $this->assertEquals($modelData->title, $model->data->title);
        $this->assertEquals(
            $modelData->bubbles[0]->id,
            $model->data->bubbles[0]->id
        );
    }
}
