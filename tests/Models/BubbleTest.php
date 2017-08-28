<?php

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Folklore\Panneau\Models\Bubble;
use Folklore\Panneau\Support\FieldsSchema;
use Folklore\Mediatheque\Models\Picture;

class BubbleTest extends TestCase
{
    protected $schema;

    protected $mediasSchema;

    public function setUp()
    {
        parent::setUp();

        $this->artisan('migrate', ['--database' => 'testing']);

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

        $this->mediasSchema = new FieldsSchema([
            'fields' => [
                'data' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => \Folklore\Panneau\Schemas\Fields\TextLocale::class,
                        'pictures' => \Folklore\Panneau\Schemas\Fields\Pictures::class,
                    ],
                    'required' => ['title']
                ]
            ]
        ]);
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
    public function testMediasRelations()
    {
        $picture = new Picture();
        $picture->setOriginalFile(__DIR__.'/../fixture/picture.jpg');
        $picture->save();

        $data = json_decode(json_encode([
            'title' => [
                'en' => 'Test'
            ],
            'pictures' => [
                $picture->toArray()
            ]
        ]));

        Bubble::setDefaultSchema($this->mediasSchema);

        $model = new Bubble();
        $model->data = $data;
        $model->save();

        $model = Bubble::with('pictures')->find($model->id);
        $this->assertEquals($data->title, $model->data->title);
        $this->assertEquals($data->pictures[0]->id, $model->data->pictures[0]->id);
    }
}
