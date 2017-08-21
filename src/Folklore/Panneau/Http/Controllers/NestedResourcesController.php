<?php

namespace Folklore\Panneau\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;
use Folklore\Panneau\Exceptions\JsonSchemaException;
use \Exception;

class NestedResourcesController extends BaseController
{
    protected $resourcesMap = [
        'block' => [
            'idField' => 'id',
            'model' => Block::class,
            'relations' => [
                'blocks' => 'block'
            ]
        ],
        'page' => [
            'idField' => 'id',
            'model' => Page::class,
            'relations' => [
                // 'children' => 'page',
                'blocks' => 'block'
            ]
        ]
    ];

    public function getResourcesMap()
    {
        return $this->resourcesMap ?? null;
    }

    protected function getTypeDefinition($type)
    {
        $map = $this->getResourcesMap();
        if (empty($map)) {
            throw new Exception('Empty resources map');
        }

        $typeDef = array_filter($this->getResourcesMap(), function ($val) use ($type) {
            return strtolower(class_basename($val['model'])) === strtolower($type);
        });
        $typeDef = reset($typeDef);
        if (!$typeDef) {
            throw new Exception('Unkown type "'.$type.'"');
        }
        return $typeDef;
    }

    protected function getRelationDefinition($type, $relation)
    {
        $typeDef = $this->getTypeDefinition($type);
        $relationDef = $typeDef['relations'][$relation];
        if (!$relationDef) {
            throw new Exception('Unkown relation "'.$relation.'" in type "'.$type.'"');
        }
        return $this->getTypeDefinition($relationDef['ref']);
    }

    protected function recursiveStore($data, $type, $returnWithRelations = false, $recursionPath = '', &$jsonSchemaErrors = [])
    {
        $typeDef = $this->getTypeDefinition($type);
        $id = $data[$typeDef['idField']] ?? null;
        $class = app($typeDef['model']);
        if ($id) {
            $model = $class::findOrFail($id);
        } else {
            $model = new $class();
        }

        $relations = $typeDef['relations'] ?? null;
        foreach ($data as $key => $value) {
            if ($relations && !array_key_exists($key, $relations)) {
                $model->{$key} = $value;
            }
        }
        try {
            $model->save();
        } catch (JsonSchemaException $e) {
            $errors = [];
            foreach ($e->getSchemaErrors() as $key => $value) {
                $errors[$recursionPath.$key] = $value;
            }
            $jsonSchemaErrors += $errors;
        }

        if ($relations) {
            foreach ($relations as $key => $value) {
                $relationDef = $this->getRelationDefinition($type, $key);
                $relationData = $data[$key] ?? null;
                $model->{$key}()->detach();
                if (!empty($relationData)) {
                    if (is_assoc($relationData)) {
                        $relationData = [$relationData];
                    }
                    foreach ($relationData as $index => $rel) {
                        $relationModel = $this->recursiveStore($rel, $value['ref'], false, $recursionPath.$key.'.'.$index.'.', $jsonSchemaErrors);
                        $model->{$key}()->attach($relationModel, array_merge(
                            ['order' => $index],
                            $value['pivotAttributes'] ?? []
                        ));
                    }
                }
            }
        }

        if ($recursionPath == '' && !empty($jsonSchemaErrors)) {
            throw new JsonSchemaException($jsonSchemaErrors);
        }

        if ($returnWithRelations && $relations) {
            return $model->fresh()->load(array_keys($relations));
        }
        return $model->fresh();
    }
}
