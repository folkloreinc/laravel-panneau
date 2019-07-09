<?php

use Illuminate\Database\Eloquent\Model;
use Panneau\Support\Interfaces\HasFieldsSchema as HasFieldsSchemaInterface;
use Panneau\Support\Traits\HasFieldsSchema;

class TestModel extends Model implements HasFieldsSchemaInterface
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

    public function getFieldsSchemaName()
    {
        return 'test';
    }
}
