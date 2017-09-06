<?php

use Folklore\Panneau\Schemas\Page as PageSchema;

/**
 */
class ReducersTest extends TestCase
{
    public function testReducersGet()
    {
        // setRawAttribute()

    }

    public function testReducers()
    {


        $data = [
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

        $nodes = $schema->getNodes()->makeFromData($data);



        dd($nodes);
    }
}
