<?php

namespace Panneau\Tests\Feature\Models;

use Panneau\Tests\TestCase;
use Panneau\Tests\RunMigrationsTrait;
use Folklore\EloquentJsonSchema\Support\JsonSchema;
use Panneau\Models\Block;

class BlockTest extends TestCase
{
    use RunMigrationsTrait;

    protected $schema;

    protected $relationsSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->runMigrations();

        $this->schema = new JsonSchema([
            'type' => 'object',
            'properties' => [
                'title' => \Panneau\Schemas\Fields\TextLocalized::class,
            ],
            'required' => ['title']
        ]);

        $this->relationsSchema = new JsonSchema([
            'type' => 'object',
            'properties' => [
                'title' => \Panneau\Schemas\Fields\TextLocalized::class,
                'blocks' => \Panneau\Schemas\Fields\Blocks::class,
            ],
            'required' => ['title']
        ]);
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
        $model = new Block();
        $model->setJsonSchema('data', $this->schema);
        $model->data = [];
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

        $model = new Block();
        $model->setJsonSchema('data', $this->schema);
        $model->data = $data;
        $model->save();

        $model = Block::find($model->id);
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

        $modelData = json_decode(json_encode([
            'title' => [
                'en' => 'Test'
            ],
            'blocks' => [
                $relation
            ]
        ]));
        $model = new Block();
        $model->setJsonSchema('data', $this->relationsSchema);
        $model->data = $modelData;
        $model->save();

        $model = Block::with('blocks')->find($model->id);
        $model->setJsonSchema('data', $this->relationsSchema);
        $this->assertEquals(1, sizeof($model->blocks));
        $this->assertEquals('data.blocks.0', $model->blocks[0]->pivot->handle);
        $this->assertEquals($modelData->title, $model->data->title);
        $this->assertEquals(
            $modelData->blocks[0]->id,
            $model->data->blocks[0]->id
        );
    }
}
