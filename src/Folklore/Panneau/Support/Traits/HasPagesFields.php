<?php

namespace Folklore\Panneau\Support\Traits;

use Folklore\Panneau\Support\Reducers\PagesReducer;

trait HasPagesFields
{
    public static function bootHasPagesFields()
    {
        static::addReducer(PagesReducer::class);
    }
}
