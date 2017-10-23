<?php

namespace Folklore\Panneau\Reducers;

use Folklore\EloquentJsonSchema\Support\RelationReducer;

class BubblesReducer extends RelationReducer
{
    protected function getRelationClass($model, $node, $state)
    {
        return \Folklore\Panneau\Contracts\Bubble::class;
    }

    protected function getRelationSchemaClass($model, $node, $state)
    {
        return \Folklore\Panneau\Schemas\Fields\Bubble::class;
    }

    protected function getRelationSchemaManyClass($model, $node, $state)
    {
        return \Folklore\Panneau\Schemas\Fields\Bubbles::class;
    }

    protected function getRelationName($model, $node, $state)
    {
        return 'bubbles';
    }
}
