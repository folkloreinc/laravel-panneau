<?php

namespace App\Models;

use Folklore\Panneau\Models\Block as BaseBlock;

class Block extends BaseBlock
{
    protected $hidden = [
        'pictures',
        'audios',
        'videos',
        'pivot',
        'deleted_at'
    ];

    protected $jsonSchemasReducers = [
        \Folklore\Panneau\Reducers\BlocksReducer::class,
        \Folklore\Panneau\Reducers\PagesReducer::class,
        \Folklore\Panneau\Reducers\MediasReducer::class,
    ];
}
