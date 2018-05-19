<?php

namespace Folklore\Panneau\Schemas\Fields;

use Folklore\Panneau\Support\Field;

class Page extends Field
{
    protected function attributes()
    {
        return [
            'endpoint' => $this->getEndpoint(),
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
