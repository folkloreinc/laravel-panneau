<?php

namespace Folklore\Panneau\Support\Reducers;

class BubblesReducer extends RelationReducer
{
    protected function getRelationClass()
    {
        return \Folklore\Panneau\Contracts\Bubble::class;
    }

    protected function getRelationSchemaClass()
    {
        return \Folklore\Panneau\Schemas\Fields\Bubble::class;
    }

    protected function getRelationSchemaManyClass()
    {
        return \Folklore\Panneau\Schemas\Fields\Bubbles::class;
    }

    protected function getRelationName()
    {
        return 'bubbles';
    }
}
