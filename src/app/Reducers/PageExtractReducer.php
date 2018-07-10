<?php

namespace App\Reducers;

use Folklore\Panneau\Support\ExtractColumnReducer;

class PageExtractReducer extends ExtractColumnReducer
{
    protected function getExtractedColumns()
    {
        return [
            'slug_fr' => 'data.slug.fr',
            'slug_en' => 'data.slug.en',
        ];
    }
}
