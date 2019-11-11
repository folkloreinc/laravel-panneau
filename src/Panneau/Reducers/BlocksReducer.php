<?php

namespace Panneau\Reducers;

use Folklore\EloquentJsonSchema\Support\RelationReducer;

class BlocksReducer extends RelationReducer
{
    protected function getRelationSchemaClass($model, $node, $state)
    {
        return \Panneau\Schemas\Fields\Block::class;
    }

    protected function getRelationName($model, $node, $state)
    {
        return 'blocks';
    }

    protected function shouldUpdateRelation($model, $relation)
    {
        return true;
    }
}
