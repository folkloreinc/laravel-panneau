<?php

namespace Folklore\Panneau\Support\Traits;

trait HasBubblesFields
{
    protected function prepareBubblesField($path, $value, $field)
    {
        return $this->prepareRelationsField('bubbles', $path, $value, $field);
    }

    protected function preparedBubbleField($path, $value, $field)
    {
        return $this->prepareRelationField('bubbles', $path, $value, $field);
    }

    protected function saveBubblesField($path, $value, $originalValue, $field)
    {
        return $this->saveRelationsField('bubbles', $path, $value, $originalValue, $field);
    }

    protected function savedBubbleField($path, $value, $originalValue, $field)
    {
        return $this->saveRelationField('bubbles', $path, $value, $originalValue, $field);
    }

    protected function getBubblesField($path, $value, $fieldValue, $field)
    {
        return $this->getRelationsField('bubbles', $path, $value, $fieldValue, $field);
    }

    protected function getdBubbleField($path, $value, $fieldValue, $field)
    {
        return $this->getRelationField('bubbles', $path, $value, $fieldValue, $field);
    }

    protected function shouldUpdateBubblesRelationItem($relation, $id, $value)
    {
        return true;
    }
}
