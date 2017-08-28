<?php

namespace Folklore\Panneau\Support\Traits;

trait HasMediasFields
{
    protected function preparePicturesField($path, $value, $field)
    {
        return $this->prepareMediasField($path, $value, $field);
    }

    protected function preparePictureField($path, $value, $field)
    {
        return $this->prepareMediaField($path, $value, $field);
    }

    protected function savePicturesField($path, $value, $originalValue, $field)
    {
        return $this->saveMediasField('pictures', $path, $value, $originalValue, $field);
    }

    protected function savePictureField($path, $value, $originalValue, $field)
    {
        return $this->saveMediaField('pictures', $path, $value, $originalValue, $field);
    }

    protected function getPicturesField($path, $fieldValue, $value, $field)
    {
        return $this->getMediasField('pictures', $path, $fieldValue, $value, $field);
    }

    protected function getPictureField($path, $fieldValue, $value, $field)
    {
        return $this->getMediaField('pictures', $path, $fieldValue, $value, $field);
    }

    protected function prepareMediasField($path, $value, $field)
    {
        $ids = [];
        foreach ($value as $item) {
            if (!is_object($item) && !is_array($item)) {
                $ids[] = $item;
                continue;
            }
            $ids[] = is_array($item) ? $item['id'] : $item->id;
        }
        return $ids;
    }

    protected function prepareMediaField($path, $value, $field)
    {
        if (!is_object($value) && !is_array($value)) {
            return $value;
        }
        return is_array($value) ? $value['id'] : $value->id;
    }

    protected function saveMediasField($relation, $path, $value, $originalValue, $field)
    {
        if ($value !== $originalValue) {
            $ids = [];
            $query = $this->{$relation}();
            $currentMedias = $query->where($query->getTable().'.handle', 'LIKE', $path.'.%')->get();
            $currentHandles = [];
            foreach ($currentMedias as $media) {
                $currentHandles[$media->pivot->handle] = (string)$media->id;
            }
            if (isset($value) && sizeof($value)) {
                $i = 0;
                foreach ($value as $id) {
                    $handle = $path.'.'.$i;
                    if (!isset($currentHandles[$handle]) || $currentHandles[$handle] !== (string)$id) {
                        $this->{$relation}()->attach($id, [
                            'handle' => $handle
                        ]);
                    }
                    $ids[$handle] = (string)$id;
                    $i++;
                }

                $detachIds = [];
                foreach ($currentHandles as $handle => $id) {
                    if (!isset($ids[$handle]) || $ids[$handle] !== $id) {
                        $detachIds[] = $id;
                    }
                }
                if (sizeof($detachIds)) {
                    $this->{$relation}()->detach($detachIds);
                }
            } elseif (sizeof($currentMedias)) {
                $this->{$relation}()->detach(array_values($currentMedias));
            }
        }
    }

    protected function saveMediaField($relation, $path, $value, $originalValue, $field)
    {
        $query = $this->{$relation}();
        if (is_null($value)) {
            $currentMedia = $query->where($query->getTable().'.handle', '=', $path)->first();
            if ($currentMedia) {
                $query->detach($currentMedia->id);
            }
            return;
        }
        if ($value !== $originalValue) {
            $currentMedia = $query->where($query->getTable().'.handle', '=', $path)->first();
            $id = (string)$value;
            if ((string)$currentMedia->id !== $id) {
                $this->{$relation}()->attach($id, [
                    'handle' => $path
                ]);
                if ($currentMedia) {
                    $query->detach($currentMedia->id);
                }
            }
        }
    }

    protected function getMediasField($relation, $path, $fieldValue, $value, $field)
    {
        $items = [];
        $query = $this->{$relation}();
        $currentMedias = $query->where($query->getTable().'.handle', 'LIKE', $path.'.%')->get();
        foreach ($fieldValue as $id) {
            $item = $currentMedias->first(function ($item) use ($id) {
                return $item->id === $id;
            });
            if (isset($item)) {
                $items[] = $item;
            }
        }
        return $items;
    }

    protected function getMediaField($relation, $path, $fieldValue, $value, $field)
    {
        if (is_object($fieldValue)) {
            return $fieldValue;
        }

        return $this->{$relation}->first(function ($item) use ($path) {
            return $item->pivot->handle === $path;
        });
    }
}
