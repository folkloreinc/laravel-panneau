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

        $this->pageSchema = new FieldsSchema([
            'fields' => [
                'data' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => \Folklore\Panneau\Schemas\Fields\Text::class,
                        'title' => \Folklore\Panneau\Schemas\Fields\Text::class,
                    ],
                    'required' => ['title']
                ]
            ]
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

    public function testReducersGet()
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
                    'title' => '11111',
                    'description' => 'Lorem ipsum dolor sit amet consectuet'
                ]
            ],
            [
                'data' => [
                    'title' => '22222',
                    'description' => 'Lorem ipsum dolor sit amet consectuet'
                ]
            ]
        ];

        $model = PageModel::create($page);
        $model->save();
        $pageId = $model->id;
        $blockIds = [];
        foreach ($blocks as $block) {
            $model = BlockModel::create($page);
            $model->save();
            $blockIds[] = $model->id;
        }

        $sourceData = [
            'title' => 'My test title',
            'slug' => 'my-test-title',
            'parent' => $pageId,
            'blocks' => $blockIds
        ];

        // Test from raw data (equivalent to database)
        $model = new PageModel();
        $model->setRawAttributes([
            'data' => json_encode($sourceData)
        ]);

        $this->assertEquals(array_get($sourceData, 'title'), $model->data->title);
        $this->assertInstanceOf(PageModel::class, $model->data->parent);
        $i = 0;
        foreach ($model->data->blocks as $block) {
            $this->assertInstanceOf(BlockModel::class, $block);
            $this->assertEquals(array_get($sourceData, 'blocks.'.$i), $block->id);
            $i++;
        }
        $i = 0;
        foreach ($model->blocks as $block) {
            $this->assertEquals('data.blocks.'.$i, $block->pivot->handle);
            $i++;
        }

        // Test from array/object data using fill (equivalent to post)
        // Convert blocks to object { "id" : <ID> }
        $sourceData['blocks'] = array_map(function ($block) {
            return [
                'id' => $block->id,
            ];
        }, $sourceData['blocks']);
        $model = new PageModel();
        $model->fill([
            'data' => $sourceData
        ]);

        $this->assertEquals(array_get($sourceData, 'title'), $model->data->title);
        $this->assertInstanceOf(PageModel::class, $model->data->parent);
        $i = 0;
        foreach ($model->data->blocks as $block) {
            $this->assertInstanceOf(BlockModel::class, $block);
            $this->assertEquals(array_get($sourceData, 'blocks.'.$i.'.id'), $block->id);
            $i++;
        }
        $i = 0;
        foreach ($model->blocks as $block) {
            $this->assertEquals('data.blocks.'.$i, $block->pivot->handle);
            $i++;
        }
    }

    public function _testReducers()
    {
        $sourceData = [
            'data' => [
                'title' => 'My test title',
                'blocks' => [
                    [
                        'type' => 'text',
                        'data' => [
                            'description' => 'My first block'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'description' => 'My second block'
                        ]
                    ],
                    [
                        'type' => 'image',
                        'data' => [
                            'picture' => [
                                'id' => 1
                            ]
                        ]
                    ],
                ]
            ]
        ];
        $schema = new PageSchema();
        $nodes = $schema->getNodes()->makeFromData($sourceData);
        dd($nodes);
    }
}
