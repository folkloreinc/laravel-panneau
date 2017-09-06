<?php

use Folklore\Panneau\Models\Page as PageModel;
use Folklore\Panneau\Schemas\Page as PageSchema;
use Illuminate\Support\Facades\DB;

/**
 */
class ReducersTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->artisan('migrate', ['--database' => 'testing']);
    }

    public function testReducersGet()
    {
        DB::table('panneau_pages')->insert([
            'id' => 1,
            'data' => json_encode([
                'type' => 'hub',
                'data' => [
                    'title' => 'Hub page',
                    'slug' => 'hub-page',
                ]
            ])
        ]);
        DB::table('panneau_blocks')->insert([
            'id' => 1,
            'data' => json_encode([
                'type' => 'text',
                'data' => [
                    'title' => '11111',
                    'description' => 'Lorem ipsum dolor sit amet consectuet'
                ]
            ])
        ]);
        DB::table('panneau_blocks')->insert([
            'id' => 2,
            'data' => json_encode([
                'type' => 'text',
                'data' => [
                    'title' => '22222',
                    'description' => 'Lorem ipsum dolor sit amet consectuet'
                ]
            ])
        ]);

        $sourceData = [
            'type' => 'hub',
            'data' => [
                'title' => 'My test title',
                'slug' => 'my-test-title',
                'parent' => 1,
                'blocks' => [
                    1,
                    2,
                ]
            ]
        ];
        $jsonData = json_encode($sourceData);
        $model = new PageModel();
        $model->setRawAttributes(['data' => $jsonData]);
        dd('------', 'test end', $model->data);
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
