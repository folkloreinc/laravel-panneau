<?php

namespace Panneau\Reducers;

use Folklore\EloquentJsonSchema\Support\RelationReducer;
use Folklore\Mediatheque\Contracts\Models\Media as MediaContract;
use Panneau\Schemas\Fields\Audio as AudioSchema;
use Panneau\Schemas\Fields\Audios as AudiosSchema;
use Panneau\Schemas\Fields\Document as DocumentSchema;
use Panneau\Schemas\Fields\Documents as DocumentsSchema;
use Panneau\Schemas\Fields\Picture as PictureSchema;
use Panneau\Schemas\Fields\Pictures as PicturesSchema;
use Panneau\Schemas\Fields\Video as VideoSchema;
use Panneau\Schemas\Fields\Videos as VideosSchema;

class MediasReducer extends RelationReducer
{
    protected function getRelationClass($model, $node, $state)
    {
        if ($node->schema instanceof AudioSchema || $node->schema instanceof AudiosSchema) {
            return MediaContract::class;
        } elseif ($node->schema instanceof DocumentSchema || $node->schema instanceof DocumentsSchema) {
            return MediaContract::class;
        } elseif ($node->schema instanceof PictureSchema || $node->schema instanceof PicturesSchema) {
            return MediaContract::class;
        } elseif ($node->schema instanceof VideoSchema || $node->schema instanceof VideosSchema) {
            return MediaContract::class;
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
            return 'medias';
        } elseif ($node->schema instanceof DocumentSchema || $node->schema instanceof DocumentsSchema) {
            return 'medias';
        } elseif ($node->schema instanceof PictureSchema || $node->schema instanceof PicturesSchema) {
            return 'medias';
        } elseif ($node->schema instanceof VideoSchema || $node->schema instanceof VideosSchema) {
            return 'medias';
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
