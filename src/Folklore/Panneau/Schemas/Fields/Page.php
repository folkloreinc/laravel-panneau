<?php

namespace Folklore\Panneau\Schemas\Fields;

use Folklore\Panneau\Support\Field;

class Page extends Field
{
    protected function attributes()
    {
        return [
            'cardItemMap' => [
                'name' => 'title',
                'created_at' => 'created_at',
                'thumbnail' => 'image',
            ],
            'autosuggestProps' => [
                'suggestionsEndpoint' => $this->getEndpoint(),
                'suggestionValuePath' => 'title',
                'suggestionTitlePath' => 'title',
            ]
        ];
    }

    protected function fieldType()
    {
        return 'item';
    }

    protected function properties()
    {
        return [
            'id' => [
                'type' => 'integer'
            ],
        ];
    }

    protected function getEndpoint()
    {
        $routes = app('panneau')->getRoutesForResource('pages');
        return array_get($routes, 'index');
    }
}
