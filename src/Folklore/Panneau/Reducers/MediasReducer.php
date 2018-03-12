<?php

namespace Folklore\Panneau\Reducers;

use Folklore\EloquentJsonSchema\Support\RelationReducer;
use Folklore\Mediatheque\Contracts\Models\Audio as AudioContract;
use Folklore\Mediatheque\Contracts\Models\Document as DocumentContract;
use Folklore\Mediatheque\Contracts\Models\Picture as PictureContract;
use Folklore\Mediatheque\Contracts\Models\Video as VideoContract;
use Folklore\Panneau\Schemas\Fields\Audio as AudioSchema;
use Folklore\Panneau\Schemas\Fields\Audios as AudiosSchema;
use Folklore\Panneau\Schemas\Fields\Document as DocumentSchema;
use Folklore\Panneau\Schemas\Fields\Documents as DocumentsSchema;
use Folklore\Panneau\Schemas\Fields\Picture as PictureSchema;
use Folklore\Panneau\Schemas\Fields\Pictures as PicturesSchema;
use Folklore\Panneau\Schemas\Fields\Video as VideoSchema;
use Folklore\Panneau\Schemas\Fields\Videos as VideosSchema;

class MediasReducer extends RelationReducer
{
    protected function getRelationClass($model, $node, $state)
    {
        if ($node->schema instanceof AudioSchema || $node->schema instanceof AudiosSchema) {
            return AudioContract::class;
        } elseif ($node->schema instanceof DocumentSchema || $node->schema instanceof DocumentsSchema) {
            return DocumentContract::class;
        } elseif ($node->schema instanceof PictureSchema || $node->schema instanceof PicturesSchema) {
            return PictureContract::class;
        } elseif ($node->schema instanceof VideoSchema || $node->schema instanceof VideosSchema) {
            return VideoContract::class;
        }
        return null;
    }

    protected function getRelationSchemaClass($model, $node, $state)
    {
        if ($node->schema instanceof AudioSchema) {
            return AudioSchema::class;
        } elseif ($node->schema instanceof DocumentSchema) {
            return DocumentSchema::class;
        } elseif ($node->schema instanceof PictureSchema) {
            return PictureSchema::class;
        } elseif ($node->schema instanceof VideoSchema) {
            return VideoSchema::class;
        }
        return null;
    }

    protected function getRelationSchemaManyClass($model, $node, $state)
    {
        if ($node->schema instanceof AudiosSchema) {
            return AudiosSchema::class;
        } elseif ($node->schema instanceof DocumentsSchema) {
            return DocumentsSchema::class;
        } elseif ($node->schema instanceof PicturesSchema) {
            return PicturesSchema::class;
        } elseif ($node->schema instanceof VideosSchema) {
            return VideosSchema::class;
        }
        return null;
    }

    protected function getRelationName($model, $node, $state)
    {
        if ($node->schema instanceof AudioSchema || $node->schema instanceof AudiosSchema) {
            return 'audios';
        } elseif ($node->schema instanceof DocumentSchema || $node->schema instanceof DocumentsSchema) {
            return 'documents';
        } elseif ($node->schema instanceof PictureSchema || $node->schema instanceof PicturesSchema) {
            return 'pictures';
        } elseif ($node->schema instanceof VideoSchema || $node->schema instanceof VideosSchema) {
            return 'videos';
        }
        return null;
    }

    protected function mutateRelationObjectToId($model, $relation, $object)
    {
        $id = parent::mutateRelationObjectToId($model, $relation, $object);
        if (is_string($id) && !is_numeric($id) && file_exists($id)) {
            $name = basename($id);
            $item = $model->{$relation}()->where('name', $name)->first();
            if (!$item) {
                $item = $model->{$relation}()->getRelated()->newInstance();
                $item->setOriginalFile($id);
                $item->save();
            }
            return $item ? $item->id : null;
        }
        return $id;
    }
}
