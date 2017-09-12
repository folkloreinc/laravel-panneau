<?php

namespace Folklore\Panneau\Support\Traits;

trait HasPagesFields
{
    protected function preparePagesField($path, $value, $field)
    {
        return $this->prepareRelationsField('pages', $path, $value, $field);
    }

    protected function preparePageField($path, $value, $field)
    {
        return $this->prepareRelationField('pages', $path, $value, $field);
    }

    protected function savePagesField($path, $value, $originalValue, $field)
    {
        return $this->saveRelationsField('pages', $path, $value, $originalValue, $field);
    }

    protected function savePageField($path, $value, $originalValue, $field)
    {
        return $this->saveRelationField('pages', $path, $value, $originalValue, $field);
    }

    protected function getPagesField($path, $value, $fieldValue, $field)
    {
        return $this->getRelationsField('pages', $path, $value, $fieldValue, $field);
    }

    protected function getPageField($path, $value, $fieldValue, $field)
    {
        return $this->getRelationField('pages', $path, $value, $fieldValue, $field);
    }
}
