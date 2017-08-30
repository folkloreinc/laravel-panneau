<?php

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Folklore\Panneau\Models\Page;
use Folklore\Panneau\Support\FieldsSchema;
use Folklore\Panneau\Schemas\Page as PageSchema;
use Folklore\Panneau\Schemas\PageData;
use Folklore\Mediatheque\Models\Picture;
use Folklore\Mediatheque\Models\Document;
use Folklore\Panneau\Schemas\Fields\Pictures as PicturesField;
use Folklore\Panneau\Schemas\Fields\Documents as DocumentsField;

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
                    ->addProperty('documents', DocumentsField::class)
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
     * @covers \Folklore\Panneau\Support\Traits\HasFieldsSchema::setDefaultSchema
     */
    public function testMediasRelations()
    {
        $picture = new Picture();
        $picture->setOriginalFile(__DIR__.'/../fixture/picture.jpg');
        $picture->save();
        $pictureData = $picture->toArray();

        $document = new Document();
        $document->setOriginalFile(__DIR__.'/../fixture/document.pdf');
        $document->save();
        $documentData = $document->toArray();

        $data = json_decode(json_encode([
            'title' => [
                'en' => 'Test'
            ],
            'pictures' => [
                $pictureData
            ],
            'documents' => [
                $documentData
            ]
        ]));

        Page::setDefaultSchema($this->mediasSchema);

        $model = new Page();
        $model->data = $data;
        $model->save();

        $model = Page::with(['pictures', 'documents'])->find($model->id);
        $this->assertEquals($data->title, $model->data->title);
        $this->assertEquals(
            array_only($pictureData, ['id']),
            array_only($model->data->pictures[0]->toArray(), ['id'])
        );
        $this->assertEquals(
            array_only($documentData, ['id']),
            array_only($model->data->documents[0]->toArray(), ['id'])
        );
    }
}
