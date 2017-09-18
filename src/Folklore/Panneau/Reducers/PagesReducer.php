<?php

namespace Folklore\Panneau\Reducers;

class PagesReducer extends RelationReducer
{
    protected function getRelationClass($model, $node, $state)
    {
        return \Folklore\Panneau\Contracts\Page::class;
    }

    protected function getRelationSchemaClass($model, $node, $state)
    {
        return \Folklore\Panneau\Schemas\Fields\Page::class;
    }

    protected function getRelationSchemaManyClass($model, $node, $state)
    {
        return \Folklore\Panneau\Schemas\Fields\Pages::class;
    }

    protected function getRelationName($model, $node, $state)
    {
        return 'pages';
    }
}
