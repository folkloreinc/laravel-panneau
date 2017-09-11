<?php

namespace Folklore\Panneau\Support\Reducers;

class BlocksReducer extends RelationReducer
{
    protected function getRelationClass()
    {
        return \Folklore\Panneau\Contracts\Block::class;
    }

    protected function getRelationSchemaClass()
    {
        return \Folklore\Panneau\Schemas\Fields\Block::class;
    }

    protected function getRelationSchemaManyClass()
    {
        return \Folklore\Panneau\Schemas\Fields\Blocks::class;
    }

    protected function getRelationName()
    {
        return 'blocks';
    }
}
