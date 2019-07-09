<?php

namespace Panneau\Reducers;

use Folklore\EloquentJsonSchema\Support\RelationReducer;

class DocumentsReducer extends RelationReducer
{
    protected function getRelationClass($model, $node, $state)
    {
        return \Panneau\Contracts\Models\Document::class;
    }

    protected function getRelationSchemaClass($model, $node, $state)
    {
        return \Panneau\Schemas\Fields\Document::class;
    }

    protected function getRelationSchemaManyClass($model, $node, $state)
    {
        return \Panneau\Schemas\Fields\Documents::class;
    }

    protected function getRelationName($model, $node, $state)
    {
        return 'documents';
    }
}
