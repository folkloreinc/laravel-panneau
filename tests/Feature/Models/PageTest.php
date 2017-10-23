<?php

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Folklore\EloquentJsonSchema\Support\JsonSchema;
use Folklore\Panneau\Models\Page;
use Folklore\Panneau\Models\Block;
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

        $this->schema = new PageData();

        $this->relationsSchema = with(new PageData())
            ->addProperty('blocks', BlocksField::class);
    }

    public function tearDown()
    {
        parent::tearDown();

        Page::setDefaultJsonSchema('data', null);
    }

    /**
     * Test with invalid data
     *
     * @expectedException \Folklore\EloquentJsonSchema\ValidationException
     */
    public function testInvalidData()
    {
        $model = new Page();
        $model->setJsonSchema('data', $this->schema);
        $model->data = [
            'title' => 1
        ];
        $model->save();
    }

    /**
     * Test with valid data
     *
     */
    public function testValidData()
    {
        $data = json_decode(json_encode([
            'title' => [
                'en' => 'Test'
            ]
        ]));

        $model = new Page();
        $model->setJsonSchema('data', $this->schema);
        $model->data = $data;
        $model->save();

        $model = Page::find($model->id);
        $this->assertEquals($data, $model->data);
    }

    /**
     * Test with valid data
     *
     */
    public function testBlocksRelations()
    {
        Page::setDefaultJsonSchema('data', $this->relationsSchema);

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
