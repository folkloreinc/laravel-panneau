<?php

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Folklore\Panneau\Models\Page;
use Folklore\Panneau\Support\FieldsSchema;
use Folklore\Panneau\Schemas\Page as PageSchema;
use Folklore\Panneau\Schemas\PageData;
use Folklore\Mediatheque\Models\Picture;
use Folklore\Panneau\Schemas\Fields\Pictures as PicturesField;

class PageTest extends TestCase
{
    protected $schema;

    protected $mediasSchema;

    public function setUp()
    {
        parent::setUp();

        $this->artisan('migrate', ['--database' => 'testing']);

        $this->schema = new PageSchema();

        $this->mediasSchema = new FieldsSchema([
            'fields' => [
                'data' => with(new PageData())
                    ->addProperty('pictures', PicturesField::class)
            ]
        ]);
    }

    public function tearDown()
    {
        parent::tearDown();

        Page::setDefaultSchema(null);
    }

    /**
     * Test with invalid data
     *
     * @expectedException \Folklore\Panneau\Exceptions\SchemaValidationException
     * @covers \Folklore\Panneau\Support\Traits\HasFieldsSchema::setSchema
     */
    public function testInvalidData()
    {
        $model = new Page();
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

        $model = new Page();
        $model->setSchema($this->schema);
        $model->data = $data;
        $model->save();

        $model = Page::find($model->id);
        $this->assertEquals($data, $model->data->getValue());
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
        $pictureData = $picture->toArray();

        $data = json_decode(json_encode([
            'title' => [
                'en' => 'Test'
            ],
            'pictures' => [
                $pictureData
            ]
        ]));

        Page::setDefaultSchema($this->mediasSchema);

        $model = new Page();
        $model->data = $data;
        $model->save();

        $model = Page::with('pictures')->find($model->id);
        $this->assertEquals($data->title, $model->data->title);
        $this->assertEquals(
            array_only($pictureData, ['id']),
            array_only($model->data->pictures[0]->toArray(), ['id'])
        );
    }
}
