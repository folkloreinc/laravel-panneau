<?php

namespace Folklore\Panneau\Reducers;

use Folklore\Mediatheque\Contracts\Models\Picture as PictureContract;
use Folklore\Mediatheque\Contracts\Models\Document as DocumentContract;
use Folklore\Panneau\Schemas\Fields\Picture as PictureSchema;
use Folklore\Panneau\Schemas\Fields\Pictures as PicturesSchema;
use Folklore\Panneau\Schemas\Fields\Document as DocumentSchema;
use Folklore\Panneau\Schemas\Fields\Documents as DocumentsSchema;

class MediasReducer extends RelationReducer
{
    protected function getRelationClass($model, $node, $state)
    {
        if ($node->schema instanceof PictureSchema || $node->schema instanceof PicturesSchema) {
            return PictureContract::class;
        } elseif ($node->schema instanceof DocumentSchema || $node->schema instanceof DocumentsSchema) {
            return DocumentContract::class;
        }
        return null;
    }

    protected function getRelationSchemaClass($model, $node, $state)
    {
        if ($node->schema instanceof PictureSchema) {
            return PictureSchema::class;
        } elseif ($node->schema instanceof DocumentSchema) {
            return DocumentSchema::class;
        }
        return null;
    }

    protected function getRelationSchemaManyClass($model, $node, $state)
    {
        if ($node->schema instanceof PicturesSchema) {
            return PicturesSchema::class;
        } elseif ($node->schema instanceof DocumentsSchema) {
            return DocumentsSchema::class;
        }
        return null;
    }

    protected function getRelationName($model, $node, $state)
    {
        if ($node->schema instanceof PictureSchema || $node->schema instanceof PicturesSchema) {
            return 'pictures';
        } elseif ($node->schema instanceof DocumentSchema || $node->schema instanceof DocumentsSchema) {
            return 'documents';
        }
        return null;
    }
}
