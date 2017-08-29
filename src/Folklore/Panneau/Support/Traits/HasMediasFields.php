<?php

namespace Folklore\Panneau\Support\Traits;

trait HasMediasFields
{
    protected function preparePicturesField($path, $value, $field)
    {
        return $this->prepareRelationsField('pictures', $path, $value, $field);
    }

    protected function preparePictureField($path, $value, $field)
    {
        return $this->prepareRelationField('pictures', $path, $value, $field);
    }

    protected function savePicturesField($path, $value, $originalValue, $field)
    {
        return $this->saveRelationsField('pictures', $path, $value, $originalValue, $field);
    }

    protected function savePictureField($path, $value, $originalValue, $field)
    {
        return $this->saveRelationField('pictures', $path, $value, $originalValue, $field);
    }

    protected function getPicturesField($path, $value, $fieldValue, $field)
    {
        return $this->getRelationsField('pictures', $path, $value, $fieldValue, $field);
    }

    protected function getPictureField($path, $value, $fieldValue, $field)
    {
        return $this->getRelationField('pictures', $path, $value, $fieldValue, $field);
    }
}
