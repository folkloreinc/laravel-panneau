<?php

namespace Folklore\Panneau\Support\Traits;

use Folklore\Panneau\Support\Reducers\BubblesReducer;

trait HasBubblesFields
{
    public static function bootHasBubblesFields()
    {
        static::addReducer(BubblesReducer::class);
    }
}
