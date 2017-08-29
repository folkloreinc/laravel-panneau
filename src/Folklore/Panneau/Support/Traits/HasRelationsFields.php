<?php

namespace Folklore\Panneau\Support\Traits;

trait HasRelationsFields
{
    protected function prepareRelationsField($relation, $path, $value, $field)
    {
        return $this->getRelationsIdsFromValue($relation, $value, $path);
    }

    protected function prepareRelationField($relation, $path, $value, $field)
    {
        return $this->getRelationIdFromValue($relation, $value, $path);
    }

    protected function saveRelationsField($relation, $path, $value, $originalValue, $field)
    {
        if ($value !== $originalValue) {
            $ids = [];
            $currentPathsMap = $this->getRelationCurrentItemsPathMap($relation, $path);
            if (isset($value) && sizeof($value)) {
                $i = 0;
                foreach ($value as $id) {
                    $handle = $path.'.'.$i;
                    if (!isset($currentPathsMap[$handle]) || $currentPathsMap[$handle] !== (string)$id) {
                        $this->attachRelation($relation, $id, $handle);
                    }
                    $ids[$handle] = (string)$id;
                    $i++;
                }

                $detachIds = [];
                foreach ($currentPathsMap as $handle => $id) {
                    if (!isset($ids[$handle]) || $ids[$handle] !== $id) {
                        $detachIds[] = $id;
                    }
                }
                if (sizeof($detachIds)) {
                    $this->detachRelation($relation, $detachIds);
                }
            } elseif (sizeof($currentPathsMap)) {
                $this->detachRelation($relation, array_values($currentPathsMap));
            }
        }
    }

    protected function getRelationsField($relation, $path, $value, $fieldValue, $field)
    {
        if (is_null($value)) {
            return $value;
        }
        $items = [];
        foreach ($value as $id) {
            if (is_object($id)) {
                $items[] = $id;
                continue;
            }
            $item = $this->getRelationItemFromId($relation, $id);
            if (isset($item)) {
                $items[] = $item;
            }
        }
        return $items;
    }

    protected function getRelationField($relation, $path, $value, $fieldValue, $field)
    {
        if (is_object($value)) {
            return $value;
        }

        return $this->getRelationItemFromId($relation, $value);
    }

    protected function getRelationCurrentItemsPathMap($relation, $path)
    {
        $currentItems = $this->getRelationCurrentItems($relation, $path);
        $pathsMap = [];
        foreach ($currentItems as $item) {
            $path = $this->getRelationPathFromItem($relation, $item);
            $id = $this->getRelationIdFromItem($relation, $item);
            $pathsMap[$path] = $id;
        }
        return $pathsMap;
    }

    protected function saveRelationField($relation, $path, $value, $originalValue, $field)
    {
        if (is_null($value)) {
            $currentItem = $this->getRelationCurrentItem($relation, $path);
            $currentItemId = $this->getRelationIdFromItem($relation, $currentItem);
            if (!is_null($currentItemId)) {
                $this->detachRelation($relation, $currentItemId);
            }
            return;
        }
        if ($value !== $originalValue) {
            $id = (string)$value;
            $currentItem = $this->getRelationCurrentItem($relation, $path);
            $currentItemId = $this->getRelationIdFromItem($relation, $currentItem);
            if ($currentItemId !== $id) {
                $this->attachRelation($relation, $id, $path);
                if (!is_null($currentItemId)) {
                    $this->detachRelation($relation, $currentItemId);
                }
            }
        }
    }

    protected function getRelationPathFromItem($relation, $item)
    {
        $method = 'get'.studly_case($relation).'RelationPathFromItem';
        if (method_exists($this, $method)) {
            return $this->{$method}($relation, $item);
        }
        $column = $this->getRelationPathColumn($relation);
        return isset($item) ? (string)$item->pivot->{$column} : null;
    }

    protected function getRelationIdFromItem($relation, $item)
    {
        $method = 'get'.studly_case($relation).'RelationIdFromItem';
        if (method_exists($this, $method)) {
            return $this->{$method}($relation, $item);
        }
        return isset($item) ? (string)$item->id : null;
    }

    protected function getRelationItemFromId($relation, $id)
    {
        $method = 'get'.studly_case($relation).'RelationItemFromId';
        if (method_exists($this, $method)) {
            return $this->{$method}($relation, $id);
        }
        return $this->{$relation}->first(function ($item) use ($relation, $id) {
            return $this->getRelationIdFromItem($relation, $item) === (string)$id;
        });
    }

    protected function getRelationsIdsFromValue($relation, $values, $path)
    {
        $method = 'get'.studly_case($relation).'RelationsIdsFromValue';
        if (method_exists($this, $method)) {
            return $this->{$method}($relation, $values, $path);
        }
        if (is_null($values)) {
            return $values;
        }
        $i = 0;
        $keys = [];
        foreach ($values as $value) {
            $keys[] = $this->getRelationIdFromValue($relation, $value, $path.'.'.$i);
            $i++;
        }
        return $keys;
    }

    protected function getRelationIdFromValue($relation, $value, $path)
    {
        $method = 'get'.studly_case($relation).'RelationIdFromValue';
        if (method_exists($this, $method)) {
            return $this->{$method}($relation, $value, $path);
        }
        if (!is_object($value) && !is_array($value)) {
            return $value;
        }
        return is_array($value) ? array_get($value, 'id') : $value->id;
    }

    protected function getRelationPathColumn($relation)
    {
        $method = 'get'.studly_case($relation).'RelationPathColumn';
        if (method_exists($this, $method)) {
            return $this->{$method}($relation);
        }
        return 'handle';
    }

    protected function getRelationCurrentItem($relation, $path)
    {
        $method = 'get'.studly_case($relation).'RelationCurrentItem';
        if (method_exists($this, $method)) {
            return $this->{$method}($path);
        }
        $column = $this->getRelationPathColumn($relation);
        $query = $this->{$relation}();
        return $query->where($query->getTable().'.'.$column, '=', $path)->first();
    }

    protected function getRelationCurrentItems($relation, $path)
    {
        $method = 'get'.studly_case($relation).'RelationCurrentItems';
        if (method_exists($this, $method)) {
            return $this->{$method}($path);
        }
        $column = $this->getRelationPathColumn($relation);
        $query = $this->{$relation}();
        return $query->where($query->getTable().'.'.$column, 'LIKE', $path.'.%')->get();
    }

    protected function attachRelation($relation, $id, $path)
    {
        $method = 'attach'.studly_case($relation).'Relation';
        if (method_exists($this, $method)) {
            return $this->{$method}($id, $path);
        }
        $column = $this->getRelationPathColumn($relation);
        $pivot = [];
        $pivot[$column] = $path;
        return $this->{$relation}()->attach($id, $pivot);
    }

    protected function detachRelation($relation, $id)
    {
        $method = 'detach'.studly_case($relation).'Relation';
        if (method_exists($this, $method)) {
            return $this->{$method}($id);
        }
        return $this->{$relation}()->detach($id);
    }

    protected function detachRelationByPath($relation, $path)
    {
        $method = 'detach'.studly_case($relation).'RelationByPath';
        if (method_exists($this, $method)) {
            return $this->{$method}($path);
        }
        $currentItem = $this->getRelationCurrentItem($relation, $path);
        if ($currentItem) {
            return $this->{$relation}()->detach($currentItem->id);
        }
    }
}
