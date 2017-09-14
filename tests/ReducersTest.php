<?php

use Folklore\Panneau\Models\Page as PageModel;
use Folklore\Panneau\Models\Block as BlockModel;
use Folklore\Panneau\Support\FieldsSchema;
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

        $this->pageSchema = with(new FieldsSchema())
            ->addField('data', [
                'type' => 'object',
                'properties' => [
                    'slug' => \Folklore\Panneau\Schemas\Fields\Text::class,
                    'title' => \Folklore\Panneau\Schemas\Fields\Text::class,
                    'parent' => \Folklore\Panneau\Schemas\Fields\Page::class,
                    'blocks' => \Folklore\Panneau\Schemas\Fields\Blocks::class,
                ],
                'required' => ['title']
            ]);

        $this->blockSchema = new FieldsSchema([
            'fields' => [
                'data' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => \Folklore\Panneau\Schemas\Fields\Text::class,
                        'description' => \Folklore\Panneau\Schemas\Fields\Text::class,
                    ],
                    'required' => ['title']
                ]
            ]
        ]);

        PageModel::setDefaultSchema($this->pageSchema);
        BlockModel::setDefaultSchema($this->blockSchema);
    }

    public function tearDown()
    {
        parent::tearDown();

        PageModel::setDefaultSchema(null);
        BlockModel::setDefaultSchema(null);
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

        $model = PageModel::create($page);
        $model->save();
        $parentId = $model->id;
        $blockIds = [];
        foreach ($blocks as $block) {
            $model = BlockModel::create($block);
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

        $pageModel = PageModel::create($pageData);
        $pageModel->save();
        $pageModel->load('blocks');

        $blockId = $pageModel->data->blocks[0]->id;

        $pageModel->makeFieldDisabled('data');
        $output = $pageModel->toArray();
        $this->assertEquals($blockId, array_get($output, 'data.blocks.0'));

        $pageModel->makeFieldEnabled('data');
        $output = $pageModel->toArray();
        $this->assertEquals($blockId, array_get($output, 'data.blocks.0.id'));

        $pageModel->makeHidden('data');
        $output = $pageModel->toArray();
        $this->assertArrayNotHasKey('data', $output);
    }
}
