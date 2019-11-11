<?php

namespace Panneau\Support\Schemas;

use Folklore\EloquentJsonSchema\Support\JsonSchema;

class ModelField extends Field
{
    /**
     * The cache the model properties.
     *
     * @var array
     */
    protected static $modelPropertiesCache = [];

    public function getFieldModel()
    {
        return $this->getSchemaAttribute('fieldModel');
    }

    protected function properties()
    {
        $properties = $this->getModelProperties();
        return !is_null($properties)
            ? $properties
            : [
                'id' => [
                    'type' => 'integer'
                ]
            ];
    }

    /**
     * Get the model properties
     *
     * @return array
     */
    public function getModelProperties()
    {
        $fieldModel = $this->getFieldModel();
        if (is_null($fieldModel)) {
            return null;
        }

        $class = is_object($fieldModel) ? get_class($fieldModel) : $fieldModel;

        if (!isset(static::$modelPropertiesCache[$class])) {
            static::cacheModelProperties($class);
        }

        return static::$modelPropertiesCache[$class];
    }

    /**
     * Extract and cache all the model properties
     *
     * @param  string  $class
     * @return void
     */
    public static function cacheModelProperties($class)
    {
        static::$modelPropertiesCache[$class] = collect(
            static::getModelAttributes($class)
        )->reduce(function ($map, $attributes) {
            return array_merge($map, [
                $attributes['name'] => [
                    'type' => $attributes['type']
                ]
            ]);
        }, []);
    }

    /**
     * Get all of the model properties
     *
     * @param  mixed  $class
     * @return array
     */
    protected static function getModelAttributes($class)
    {
        $model = app($class);
        $key = $model->getKeyName();
        $casts = $model->getCasts();
        $fillable = $model->getFillable();
        $attributes = [
            [
                'name' => $key,
                'type' => 'integer'
            ]
        ];
        foreach ($fillable as $fillable) {
            $type = array_get($casts, $fillable, 'any');
            $attributes[] = [
                'name' => $fillable,
                'type' => $type,
            ];
        }
        return $attributes;
    }
}
