<?php

namespace Folklore\Panneau\Support\Traits;

use Folklore\Panneau\Support\Reducers\BlocksReducer;

trait HasBlocksFields
{
    public static function bootHasBlocksFields()
    {
        static::addReducer(BlocksReducer::class);
    }
}
