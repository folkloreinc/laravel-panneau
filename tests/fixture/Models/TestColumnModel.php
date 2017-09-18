<?php

use Illuminate\Database\Eloquent\Model;
use Folklore\Panneau\Support\Interfaces\HasFieldsSchema as HasFieldsSchemaInterface;
use Folklore\Panneau\Support\Traits\HasFieldsSchema;

class TestColumnModel extends Model implements HasFieldsSchemaInterface
{
    use HasFieldsSchema;

    protected $table = 'test';

    protected $hidden = [
        'data',
        'created_at',
        'updated_at',
        'deleted_at',
        'pivot',
    ];

    protected $guarded = [
        'id'
    ];

    protected $casts = [
        'data' => 'object',
        'type' => 'string',
    ];

    public function getFieldsSchemaNameColumn()
    {
        return 'schema';
    }
}
