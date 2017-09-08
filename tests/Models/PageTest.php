<?php

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Folklore\Panneau\Models\Page;
use Folklore\Panneau\Models\Block;
use Folklore\Panneau\Support\FieldsSchema;
use Folklore\Panneau\Schemas\Page as PageSchema;
use Folklore\Panneau\Schemas\PageData;
use Folklore\Panneau\Schemas\Fields\Blocks as BlocksField;

class PageTest extends TestCase
{
    use RunMigrationsTrait;

    protected $schema;

    protected $relationsSchema;

    public function setUp()
    {
        parent::setUp();

        $this->runMigrations();

        $this->schema = new PageSchema();

        $this->relationsSchema = new FieldsSchema([
            'fields' => [
                'data' => with(new PageData())
                    ->addProperty('blocks', BlocksField::class)
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
        $this->assertEquals($data, $model->data);
    }

    /**
     * Test with valid data
     *
     * @covers \Folklore\Panneau\Support\Traits\HasFieldsSchema::setSchema
     */
    public function testBlocksRelations()
    {
        Page::setDefaultSchema($this->relationsSchema);

        $relation = new Block();
        $relation->save();

        $modelData = json_decode(json_encode([
            'title' => [
                'en' => 'Test'
            ],
            'blocks' => [
                $relation
            ]
        ]));
        $model = new Page();
        $model->data = $modelData;
        $model->save();

        $model = Page::with('blocks')->find($model->id);
        $this->assertEquals(1, sizeof($model->blocks));
        $this->assertEquals('data.blocks.0', $model->blocks[0]->pivot->handle);
        $this->assertEquals($modelData->title, $model->data->title);
        $this->assertEquals(
            $modelData->blocks[0]->id,
            $model->data->blocks[0]->id
        );
    }
}
