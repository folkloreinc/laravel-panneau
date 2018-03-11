<?php

use Folklore\Panneau\Models\Page as PageModel;
use Folklore\Panneau\Models\Block as BlockModel;
use Folklore\EloquentJsonSchema\Support\JsonSchema;
use Illuminate\Support\Facades\DB;

/**
 */
class ReducersTest extends TestCase
{
    use RunMigrationsTrait;

    protected $schema;

    public function setUp()
    {
        parent::setUp();

        $this->runMigrations();

        $this->pageSchema = new JsonSchema([
            'type' => 'object',
            'properties' => [
                'slug' => \Folklore\Panneau\Schemas\Fields\Text::class,
                'title' => \Folklore\Panneau\Schemas\Fields\Text::class,
                'parent' => \Folklore\Panneau\Schemas\Fields\Page::class,
                'blocks' => \Folklore\Panneau\Schemas\Fields\Blocks::class,
            ],
            'required' => ['title']
        ]);

        $this->blockSchema = new JsonSchema([
            'type' => 'object',
            'properties' => [
                'title' => \Folklore\Panneau\Schemas\Fields\Text::class,
                'description' => \Folklore\Panneau\Schemas\Fields\Text::class,
            ],
            'required' => ['title']
        ]);
    }

    public function tearDown()
    {
        parent::tearDown();
    }

    public function testReducersGetSetSave()
    {
        $page = [
            'data' => [
                'title' => 'Hub page',
                'slug' => 'hub-page',
            ]
        ];
        $blocks = [
            [
                'data' => [
                    'title' => 'Block 1',
                    'description' => 'Lorem ipsum dolor sit amet consectuet'
                ]
            ],
            [
                'data' => [
                    'title' => 'Block 2',
                    'description' => 'Lorem ipsum dolor sit amet consectuet'
                ]
            ],
            [
                'data' => [
                    'title' => 'Block 3',
                    'description' => 'Lorem ipsum dolor sit amet consectuet'
                ]
            ]
        ];

        $model = new PageModel();
        $model->setJsonSchema('data', $this->pageSchema);
        $model->fill($page);
        $model->save();
        $parentId = $model->id;
        $blockIds = [];
        foreach ($blocks as $block) {
            $model = new BlockModel();
            $model->setJsonSchema('data', $this->blockSchema);
            $model->fill($block);
            $model->save();
            $blockIds[] = $model->id;
        }

        $sourceData = [
            'title' => 'My main page',
            'slug' => 'my-main-page',
            'parent' => $parentId,
            'blocks' => $blockIds
        ];

        // Test from raw data (equivalent to database)
        $model = new PageModel();
        $model->setJsonSchema('data', $this->pageSchema);
        $model->setRawAttributes([
            'data' => json_encode($sourceData)
        ]);

        $this->assertEquals(array_get($sourceData, 'title'), $model->data->title);
        $this->assertInstanceOf(PageModel::class, $model->data->parent);
        $this->assertEquals($parentId, $model->data->parent->id);

        $this->assertEquals(sizeof($sourceData['blocks']), sizeof($model->data->blocks));
        $i = 0;
        foreach ($model->data->blocks as $block) {
            $this->assertInstanceOf(BlockModel::class, $block);
            $this->assertEquals(array_get($sourceData, 'blocks.'.$i), $block->id);
            $i++;
        }

        // Test from array/object data using fill (equivalent to post)
        // Convert ids to object { "id" : <ID> }
        $sourceData['parent'] = [
            'id' => $sourceData['parent'],
        ];
        $sourceData['blocks'] = array_map(function ($block) {
            return [
                'id' => $block,
            ];
        }, $sourceData['blocks']);
        $model = new PageModel();
        $model->setJsonSchema('data', $this->pageSchema);
        $model->fill([
            'data' => $sourceData
        ]);
        $model->save();
        $model->load('pages', 'parents', 'blocks');

        $this->assertEquals(array_get($sourceData, 'title'), $model->data->title);

        $this->assertInstanceOf(PageModel::class, $model->data->parent);
        $this->assertEquals($parentId, $model->data->parent->id);

        $this->assertEquals(sizeof($sourceData['blocks']), sizeof($model->data->blocks));
        $i = 0;
        foreach ($model->data->blocks as $block) {
            $this->assertInstanceOf(BlockModel::class, $block);
            $this->assertEquals(array_get($sourceData, 'blocks.'.$i.'.id'), $block->id);
            $i++;
        }

        $relationBlocks = $model->blocks()->get();
        $this->assertEquals(sizeof($sourceData['blocks']), sizeof($relationBlocks));
        $i = 0;
        foreach ($relationBlocks as $block) {
            $this->assertInstanceOf(BlockModel::class, $block);
            $this->assertEquals(array_get($sourceData, 'blocks.'.$i.'.id'), $block->id);
            $this->assertEquals('data.blocks.'.$i, $block->pivot->handle);
            $i++;
        }


        $sourceDataMinus = $sourceData;
        // Not using unset(), which does not reorder keys
        array_splice($sourceDataMinus, 1, 1);
        $model->fill([
            'data' => $sourceDataMinus
        ]);
        $model->save();

        $relationBlocks = $model->blocks()->get();
        $this->assertEquals(sizeof($sourceDataMinus['blocks']), sizeof($relationBlocks));
        $i = 0;
        foreach ($relationBlocks as $block) {
            $this->assertInstanceOf(BlockModel::class, $block);
            $this->assertEquals(array_get($sourceDataMinus, 'blocks.'.$i.'.id'), $block->id);
            $this->assertEquals('data.blocks.'.$i, $block->pivot->handle);
            $i++;
        }
    }

    public function testReducersEnableVisible()
    {
        $pageData = [
            'data' => [
                'title' => 'Hub page',
                'slug' => 'hub-page',
                'blocks' => [
                    [
                        'data' => [
                            'title' => 'Test block',
                            'description' => 'Lorem ipsum dolor sit amet consectuet'
                        ]
                    ]
                ]
            ]
        ];

        $pageModel = new PageModel();
        $pageModel->setJsonSchema('data', $this->pageSchema);
        $pageModel->fill($pageData);
        $pageModel->save();
        $pageModel->load('blocks');

        $blockId = $pageModel->data->blocks[0]->id;

        // Disabled field should not be reduced
        $pageModel->makeJsonSchemaAttributeDisabled('data');
        $output = $pageModel->toArray();
        $this->assertEquals($blockId, array_get($output, 'data.blocks.0'));

        // Re-enabled field should be reduced
        $pageModel->makeJsonSchemaAttributeEnabled('data');
        $output = $pageModel->toArray();
        $this->assertEquals($blockId, array_get($output, 'data.blocks.0.id'));

        // Hidden attributes should not output, but sub-fields should still append
        $pageModel->setHidden([]);
        $pageModel->makeHidden(['data', 'blocks']);
        // $pageModel->setAppends([
        //     'title' => 'data->title'
        // ]);
        $output = $pageModel->toArray();
        $this->assertArrayNotHasKey('data', $output);
        $this->assertArrayNotHasKey('blocks', $output);
        //$this->assertEquals(array_get($pageData, 'data.title'), array_get($output, 'title'));

        $pageModel->makeVisible(['data', 'blocks']);
        $output = $pageModel->toArray();
        $this->assertArrayHasKey('data', $output);
        $this->assertArrayHasKey('blocks', $output);
        $this->assertEquals(1, sizeof(array_get($output, 'blocks')));
        $output = $pageModel->toArray();
    }
}
