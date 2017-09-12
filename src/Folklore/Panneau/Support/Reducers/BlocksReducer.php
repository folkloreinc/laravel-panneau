<?php

namespace Folklore\Panneau\Support\Reducers;

class BlocksReducer extends RelationReducer
{
    protected function getRelationClass($model, $node, $state)
    {
        return \Folklore\Panneau\Contracts\Block::class;
    }

    protected function getRelationSchemaClass($model, $node, $state)
    {
        return \Folklore\Panneau\Schemas\Fields\Block::class;
    }

    protected function getRelationSchemaManyClass($model, $node, $state)
    {
        return \Folklore\Panneau\Schemas\Fields\Blocks::class;
    }

    protected function getRelationName($model, $node, $state)
    {
        return 'blocks';
    }
}
