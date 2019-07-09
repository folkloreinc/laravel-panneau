<?php

namespace Panneau\Schemas\Fields;

use Panneau\Support\Schemas\ModelField;

class Document extends ModelField
{
    protected function fieldModel()
    {
        return \Panneau\Contracts\Models\Document::class;
    }

    protected function attributes()
    {
        return [
            'endpoint' => $this->getEndpoint()
        ];
    }

    protected function getEndpoint()
    {
        return app('panneau.router')
            ->getRoutesForResource('documents')
            ->getUrlByName('index');
    }
}
