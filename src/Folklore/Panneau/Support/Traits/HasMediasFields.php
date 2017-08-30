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

    protected function prepareDocumentsField($path, $value, $field)
    {
        return $this->prepareRelationsField('documents', $path, $value, $field);
    }

    protected function prepareDocumentField($path, $value, $field)
    {
        return $this->prepareRelationField('documents', $path, $value, $field);
    }

    protected function saveDocumentsField($path, $value, $originalValue, $field)
    {
        return $this->saveRelationsField('documents', $path, $value, $originalValue, $field);
    }

    protected function saveDocumentField($path, $value, $originalValue, $field)
    {
        return $this->saveRelationField('documents', $path, $value, $originalValue, $field);
    }

    protected function getDocumentsField($path, $value, $fieldValue, $field)
    {
        return $this->getRelationsField('documents', $path, $value, $fieldValue, $field);
    }

    protected function getDocumentField($path, $value, $fieldValue, $field)
    {
        return $this->getRelationField('documents', $path, $value, $fieldValue, $field);
    }
}
