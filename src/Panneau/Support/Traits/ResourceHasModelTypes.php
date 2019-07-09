<?php

namespace Panneau\Support\Traits;

use Panneau\Support\Schemas\Fields;

trait ResourceHasModelTypes
{
    use ResourceHasTypes;

    protected $modelTypes;

    abstract protected function modelTypes();

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
