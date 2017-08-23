<?php

namespace Folklore\Panneau\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Folklore\Panneau\Support\Interfaces\HasFieldsSchema as HasFieldsSchemaInterface;
use Folklore\Panneau\Support\Traits\HasFieldsSchema;
use Folklore\Panneau\Models\Observers\HasFieldsSchemaObserver;
use Folklore\Panneau\Models\Page;
use \Exception;

class Block extends Model implements
    HasFieldsSchemaInterface
{
    use SoftDeletes;
    use HasFieldsSchema;

    protected $table = 'blocks';

    protected $sortable = [
        'order_column_name' => 'order',
        'sort_when_creating' => false,
    ];

    protected $baseSchema = [
        'data' => [
            'title' => 'Block',
            'type' => 'object',
            'properties' => [
                'type' => [
                    'type' => 'string'
                ],
                'blocks' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'integer'
                    ]
                ]
            ],
            // 'additionalProperties' => false
        ]
    ];
    // extension schemas to be merged with base schema (see getSchemasForValidation)
    protected $typeSchemas = [
        'text' => [
            'properties' => [
                'title' => [
                    'type' => 'string'
                ],
                'quote' => [
                    'type' => 'string'
                ],
                'subtitle' => [
                    'type' => 'string'
                ],
                'text' => [
                    'type' => 'string'
                ],
                'invertTitle' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
                'coloredText' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
                'titleFont' => [
                    'type' => 'string',
                ],
            ]
        ],
        'textwithimage' => [
            'properties' => [
                'title' => [
                    'type' => 'string'
                ],
                'quote' => [
                    'type' => 'string'
                ],
                'subtitle' => [
                    'type' => 'string'
                ],
                'text' => [
                    'type' => 'string'
                ],
                'url' => [
                    'type' => 'string',
                ],
            ]
        ],
        'logo' => [
            'properties' => [
            ]
        ],
        'image' => [
            'properties' => [
                'title' => [
                    'type' => 'string'
                ],
                'url' => [
                    'type' => 'string',
                ],
                'background' => [
                    'type' => 'bool',
                ]
            ]
        ],
        'download' => [
            'properties' => [
                'title' => [
                    'type' => 'string'
                ],
                'url' => [
                    'type' => 'string'
                ],
            ]
        ],
    ];

    protected $hidden = [
        'data',
        'created_at',
        'updated_at',
        'deleted_at',
        'pivot',
    ];

    protected $casts = [
        'data' => 'object',
        'type' => 'string',
    ];

    protected $guarded = [
        'id'
    ];

    public static function boot()
    {
        static::observe(HasFieldsSchemaObserver::class);

        static::saved(function ($block) {
            if (!empty($block->data) && !empty($block->data->blocks)) {
                $blocks = $block->data->blocks;
                if (!is_array($blocks)) {
                    $blocks = [$blocks];
                }
                // Manually detach all then attach one by one instead of using sync(),
                // which does not allow for duplicate IDs.
                // @see https://laracasts.com/discuss/channels/eloquent/belongstomany-and-wanted-duplicates
                $block->blocks()->detach();
                foreach ($blocks as $key => $item) {
                    $block->blocks()->attach($item, ['order' => $key]);
                }
            }
        });

        parent::boot();
    }

    protected function getSchemasForValidation()
    {
        $def = $this->baseSchema;

        if (empty($this->type)) {
            return $def;
        }
        if (!isset($this->typeSchemas[$this->type])) {
            throw new Exception('Unknown block type "'.$this->type.'"');
        }

        $def['data']['properties'] = array_merge($def['data']['properties'], $this->typeSchemas[$this->type]['properties']);
        return $def;
    }

    protected function getSchemasAppends()
    {
        $appends = [];
        $schemas = $this->getSchemasForValidation();
        foreach ($schemas as $field => $schema) {
            foreach ($schema['properties'] as $prop => $value) {
                $appends[$prop] = $field.'.'.$prop;
            }
        }
        return $appends;
    }

    public function blocks()
    {
        return $this->belongsToMany(self::class, 'blocks_blocks_pivot', 'parent_block_id', 'block_id');
    }

    public function pages()
    {
        return $this->belongsToMany(Page::class, 'pages_blocks_pivot', 'block_id', 'page_id');
    }
}
