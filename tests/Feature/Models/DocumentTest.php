<?php

namespace Panneau\Tests\Feature\Models;

use Panneau\Tests\TestCase;
use Panneau\Tests\RunMigrationsTrait;
use Folklore\EloquentJsonSchema\Support\JsonSchema;
use Panneau\Models\Document;
use Panneau\Models\Block;
use Panneau\Schemas\Documents\Base as DocumentBaseSchema;
use Panneau\Schemas\Fields\Blocks as BlocksField;

class DocumentTest extends TestCase
{
    use RunMigrationsTrait;

    protected $schema;

    protected $relationsSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->runMigrations();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Test with invalid data
     *
     * @expectedException \Folklore\EloquentJsonSchema\ValidationException
     */
    public function testInvalidData()
    {
        $model = new Document();
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
        $data = [
            'title' => [
                'en' => 'Test'
            ]
        ];

        $model = new Document();
        $model->data = $data;
        $model->save();

        $model = Document::find($model->id);
        $this->assertEquals($data, $model->data);
    }

    /**
     * Test with valid data
     *
     */
    public function testBlocksRelations()
    {
        $relation = new Block();
        $relation->save();

        $modelData = [
            'title' => [
                'en' => 'Test'
            ],
            'blocks' => [
                $relation
            ]
        ];
        $model = new Document();
        $model->data = $modelData;
        $model->save();

        $model = Document::with('blocks')->find($model->id);
        $this->assertEquals(1, sizeof($model->blocks));
        $this->assertEquals('data.blocks.0', $model->blocks[0]->pivot->handle);
        $this->assertEquals($modelData->title, $model->data->title);
        $this->assertEquals(
            $modelData->blocks[0]->id,
            $model->data->blocks[0]->id
        );
    }
}
