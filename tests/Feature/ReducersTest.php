<?php

namespace Panneau\Tests\Feature;

use Panneau\Tests\TestCase;
use Panneau\Tests\RunMigrationsTrait;
use Panneau\Models\Document as DocumentModel;
use Panneau\Models\Block as BlockModel;
use Folklore\EloquentJsonSchema\Support\JsonSchema;
use Illuminate\Support\Facades\DB;

/**
 */
class ReducersTest extends TestCase
{
    use RunMigrationsTrait;

    protected $schema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->runMigrations();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testReducersGetSetSave()
    {
        $document = [
            'data' => [
                'title' => 'Hub document',
                'slug' => 'hub-document',
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

        $model = new DocumentModel();
        $model->fill($document);
        $model->save();
        $parentId = $model->id;
        $blockIds = [];
        foreach ($blocks as $block) {
            $model = new BlockModel();
            $model->fill($block);
            $model->save();
            $blockIds[] = $model->id;
        }

        $sourceData = [
            'title' => 'My main document',
            'slug' => 'my-main-document',
            'parent' => $parentId,
            'blocks' => $blockIds
        ];

        // Test from raw data (equivalent to database)
        $model = new DocumentModel();
        $model->setRawAttributes([
            'data' => json_encode($sourceData)
        ]);

        $this->assertEquals(array_get($sourceData, 'title'), $model->data['title']);
        $this->assertInstanceOf(DocumentModel::class, $model->data['parent']);
        $this->assertEquals($parentId, $model->data->parent->id);

        $this->assertEquals(sizeof($sourceData['blocks']), sizeof($model->data['blocks']));
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
        $model = new DocumentModel();
        $model->fill([
            'data' => $sourceData
        ]);
        $model->save();
        $model->load('documents', 'parents', 'blocks');

        $this->assertEquals(array_get($sourceData, 'title'), $model->data->title);

        $this->assertInstanceOf(DocumentModel::class, $model->data->parent);
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
        $documentData = [
            'data' => [
                'title' => 'Hub document',
                'slug' => 'hub-document',
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

        $documentModel = new DocumentModel();
        $documentModel->fill($documentData);
        $documentModel->save();
        $documentModel->load('blocks');

        $blockId = $documentModel->data['blocks'][0]->id;

        // Hidden attributes should not output, but sub-fields should still append
        $documentModel->setHidden([]);
        $documentModel->makeHidden(['data', 'blocks']);
        // $documentModel->setAppends([
        //     'title' => 'data->title'
        // ]);
        $output = $documentModel->toArray();
        $this->assertArrayNotHasKey('data', $output);
        $this->assertArrayNotHasKey('blocks', $output);
        //$this->assertEquals(array_get($documentData, 'data.title'), array_get($output, 'title'));

        $documentModel->makeVisible(['data', 'blocks']);
        $output = $documentModel->toArray();
        $this->assertArrayHasKey('data', $output);
        $this->assertArrayHasKey('blocks', $output);
        $this->assertEquals(1, sizeof(array_get($output, 'blocks')));
        $output = $documentModel->toArray();
    }
}
