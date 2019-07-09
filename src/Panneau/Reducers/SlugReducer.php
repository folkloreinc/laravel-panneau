<?php

namespace Panneau\Reducers;

use Panneau\Support\Reducers\SlugReducer as BaseSlugReducer;

class SlugReducer extends BaseSlugReducer
{
    protected function getSlugPaths()
    {
        $locales = panneau()->locales();
        $columns = [];
        foreach ($locales as $locale) {
            $columns['data.slug.'.$locale] = 'data.title.'.$locale;
        }
        return $columns;
    }
}
