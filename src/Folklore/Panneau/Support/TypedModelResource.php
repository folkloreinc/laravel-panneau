<?php

namespace Folklore\Panneau\Support;

abstract class TypedModelResource extends JsonSchemasTypedResource
{
    protected $modelTypes;

    protected function modelTypes()
    {
        return [];
    }

    protected function types()
    {
        $modelTypes = $this->getModelTypes();
        $types = [];
        foreach ($modelTypes as $modelType) {
            $types[] = [
                'id' => $modelType->getName(),
                'label' => $modelType instanceof Fields ? $modelType->getFieldsLabel() : $modelType->label,
            ];
        }
        return $types;
    }

    protected function jsonSchemas()
    {
        $schemas = [];
        $modelTypes = $this->getModelTypes();
        $model = $this->get('model');
        $model = is_string($model) ? resolve($model) : $model;
        foreach ($modelTypes as $modelType) {
            $type = $modelType->getName();
            $model->type = $type;
            $schemas[$type] = $this->getSchemasFromModel($model);
        }
        return $schemas;
    }
}
