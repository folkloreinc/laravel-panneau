<?php

namespace Folklore\Panneau\Support\Traits;

trait HasPagesFields
{
    // public static function bootHasPagesFields()
    // {
    //     static::addReducer([static::class, 'reducePages']);
    // }
    //
    // //
    // $fields = $shema->getFieldsNames();
    // foreach($fields as $name) {
    //     $state = $this->callFieldsReducers($name);
    //     $this->attributes[$name] = $state;
    // }
    //
    // // callFieldsReducers
    // $state = $this->attributes[$name];
    // $nodes = $shema->getNodes($name);
    // $reducers = $this->getReducers($name);
    // foreach($nodes as $node) {
    //     foreach($reducers as $reducer) {
    //         $state = call_user_func_array($reducer, [$this, $node, $state]);
    //     }
    // }
    // return $state;
    //
    //
    // public static function reducePages($model, $node, $state)
    // {
    //     switch ($node->type) {
    //         case 'Pages':
    //             $id = array_get($state, $node->path.'.id');
    //             array_set($state, $node->path, $id);
    //             break;
    //     }
    //     return $state;
    // }

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
