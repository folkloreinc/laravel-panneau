<?php

namespace Folklore\Panneau\Support\Reducers;

class PagesReducer extends RelationReducer
{
    protected function getRelationClass()
    {
        return \Folklore\Panneau\Contracts\Page::class;
    }

    protected function getRelationSchemaClass()
    {
        return \Folklore\Panneau\Schemas\Fields\Page::class;
    }

    protected function getRelationSchemaManyClass()
    {
        return \Folklore\Panneau\Schemas\Fields\Pages::class;
    }

    protected function getRelationName()
    {
        return 'pages';
    }
}
