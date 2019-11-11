<?php

namespace Panneau\Reducers;

use Folklore\EloquentJsonSchema\Support\RelationReducer;

class DocumentsReducer extends RelationReducer
{
    protected function getRelationSchemaClass($model, $node, $state)
    {
        return \Panneau\Schemas\Fields\Document::class;
    }

    protected function getRelationName($model, $node, $state)
    {
        return 'documents';
    }
}
