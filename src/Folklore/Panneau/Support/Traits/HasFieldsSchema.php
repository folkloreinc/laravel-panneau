<?php

namespace Folklore\Panneau\Support\Traits;

use Folklore\Panneau\Observers\HasFieldsSchemaObserver;
use Folklore\Panneau\Validators\SchemaValidator;
use Folklore\Panneau\Exceptions\SchemaValidationException;
use Folklore\Panneau\Support\FieldsCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;
use StdClass;

trait HasFieldsSchema
{
    protected $fieldsSchema;

    protected $schemaNameColumn = 'type';

    protected $originalFieldValue = [];

    public static function bootHasFieldsSchema()
    {
        static::observe(HasFieldsSchemaObserver::class);
    }

    public static function schemas()
    {
        return app('panneau')->schemas(static::class);
    }

    public static function schema($name = 'default')
    {
        return app('panneau')->schema($name, static::class);
    }

    public static function addSchema($name, $schema)
    {
        return app('panneau')->addSchema($name, $schema, static::class);
    }

    public static function addSchemas($schemas)
    {
        return app('panneau')->addSchemas($schemas, static::class);
    }

    public function getSchemaName()
    {
        $columnName = method_exists($this, 'getSchemaNameColumn') ?
            $this->getSchemaNameColumn() : $this->schemaNameColumn;
        return isset($this->{$columnName}) ? $this->{$columnName} : 'default';
    }

    public function getSchema()
    {
        if (isset($this->fieldsSchema)) {
            return $this->fieldsSchema;
        }

        $name = $this->getSchemaName();
        return static::schema($name);
    }

    public function setSchema($schema)
    {
        $this->fieldsSchema = $schema;
    }

    public function setSchemaNameColumn($column)
    {
        $this->schemaNameColumn = $column;
    }

    public function validateFieldsAgainstSchema()
    {
        $schema = $this->getSchema();
        $validator = app(\Folklore\Panneau\Contracts\SchemaValidator::class);
        $data = (object)$this->toArray();
        $fields = $schema->getFieldsNames();
        foreach ($fields as $field) {
            if ($this->hasCast($field) && $this->getCastType($field) === 'object' && is_array($data->{$field})) {
                $data->{$field} = (object)$data->{$field};
            }
        }
        if (!$validator->validateSchema($data, $schema)) {
            throw new SchemaValidationException($validator->getMessages());
        }
    }

    protected function getFieldValue($key, $data = null)
    {
        $data = !is_null($data) ? $data : $this;
        if (empty($key)) {
            return $data;
        }
        return array_reduce(explode('.', $key), function ($value, $key) {
            if (is_null($value) || $key === '*') {
                return $value;
            } elseif (is_object($value)) {
                return isset($value->{$key}) ? $value->{$key} : null;
            } elseif (is_array($value)) {
                return array_get($value, $key);
            }
            return null;
        }, $data);
    }

    protected function setFieldValue($key, $value, $originalValue)
    {
        $this->originalFieldValue[$key] = $originalValue;
        $keyParts = explode('.', $key);
        if (sizeof($keyParts) === 1) {
            $this->{$field} = $value;
            return;
        }

        $field = array_shift($keyParts);
        $this->{$field} = $this->setFieldValueDot($this->{$field}, implode('.', $keyParts), $value);
        return $this;
    }

    protected function setFieldValueDot($data, $path, $value)
    {
        $dataIsObject = is_object($data);
        $newData = $dataIsObject ? json_decode(json_encode($data), true) : $data;
        array_set($newData, $path, $value);
        if ($dataIsObject) {
            $newData = json_decode(json_encode($newData));
        }
        return $newData;
    }

    protected function getFieldOriginalValue($key = null)
    {
        if (is_null($key)) {
            return $this->originalFieldValue;
        }
        return array_get($this->originalFieldValue, $key);
    }

    protected function setFieldOriginalValue($key, $value = null)
    {
        if (is_null($value)) {
            $this->originalFieldValue = $key;
        } else {
            $this->originalFieldValue[$key] = $value;
        }
        return $this;
    }

    protected function clearFieldOriginalValue()
    {
        $this->originalFieldValue = [];
        return $this;
    }

    protected function getFieldRealPaths($path, $data = null)
    {
        if (sizeof(explode('*', $path)) <= 1) {
            return new Collection((array)$path);
        }
        if (is_null($data)) {
            $data = $this->toArray();
        }
        $dataArray = json_decode(json_encode($data), true);
        $dotKeys = array_keys(array_dot($dataArray));
        $matchingKeys = [];
        $pattern = !empty($path) && $path !== '*' ?
            '/^('.str_replace('\*', '[^\.]+', preg_quote($path)).')/' : '/^(.*)/';
        foreach ($dotKeys as $dotKey) {
            if (preg_match($pattern, $dotKey, $matches)) {
                if (!in_array($matches[1], $matchingKeys)) {
                    $matchingKeys[] = $matches[1];
                }
            }
        }
        return new Collection($matchingKeys);
    }

    protected function fieldsCollection($key = null, $data = null)
    {
        $schema = $this->getSchema();
        $structure = $schema->getStructure();
        $fields = new FieldsCollection();
        foreach ($structure as $path => $field) {
            $pathParts = explode('.', $path);
            if (!is_null($key) && !array_shift($pathParts) === $key) {
                continue;
            }
            $path = implode('.', $pathParts);
            $field = new Fluent($field);
            $field->path = $path;
            $field->paths = $this->getFieldRealPaths($path, $data);
            $fields->push($field);
        }
        return $fields;
    }

    public function prepareFieldsForSaving()
    {
        $this->fieldsCollection()
            ->eachPath(function ($path, $key, $field) {
                $schema = $field->schema;
                $value = $this->getFieldValue($path);
                $prepareMethod = 'prepare'.studly_case($field->type).'Field';
                if (method_exists($this, $prepareMethod)) {
                    $returnValue = $this->{$prepareMethod}($path, $value, $field);
                } elseif (is_object($schema) && method_exists($schema, 'prepareField')) {
                    $returnValue = $schema->prepareField($path, $value, $field, $this);
                }
                if (isset($returnValue)) {
                    $this->setFieldValue($path, $returnValue, $value);
                }
            });
    }

    public function saveFields()
    {
        $this->fieldsCollection()
            ->eachPath(function ($path, $key, $field) {
                $schema = $field->schema;
                $value = $this->getFieldValue($path);
                $originalValue = $this->getFieldOriginalValue($path);
                $saveMethod = 'save'.studly_case($field->type).'Field';
                if (method_exists($this, $saveMethod)) {
                    $returnValue = $this->{$saveMethod}($path, $value, $originalValue, $field);
                } elseif (is_object($schema) && method_exists($schema, 'saveField')) {
                    $returnValue = $schema->saveField($path, $value, $originalValue, $field, $this);
                }
            });

        $this->clearFieldOriginalValue();
    }

    public function attributeHasSchema($key)
    {
        $schema = $this->getSchema();
        $fields = $schema->getFieldsNames();
        return in_array($key, $fields);
    }

    /**
     * Prepare the fields in attributes according to the schema and accessors methods
     *
     * @param  array  $attributes
     * @return array  $attributes
     */
    public function prepareFieldsInAttributes(array $attributes)
    {
        $newAttributes = [];
        foreach ($attributes as $key => $value) {
            if ($this->attributeHasSchema($key)) {
                if ($this->isJsonCastable($key)) {
                    $value = $this->castAttribute($key, $value);
                }
                $attribute = new StdClass();
                $attribute->key = $key;
                $attribute->value = $value;
                $this->fieldsCollection($key, $value)
                    ->eachPath(function ($path, $key, $field) use ($attribute) {
                        $value = $attribute->value;
                        $schema = $field->schema;
                        $fieldValue = $this->getFieldValue($path, $value);
                        $getMethod = 'get'.studly_case($field->type).'Field';
                        if (method_exists($this, $getMethod)) {
                            $returnValue = $this->{$getMethod}($path, $fieldValue, $value, $field);
                        } elseif (is_object($schema) && method_exists($schema, 'getField')) {
                            $returnValue = $schema->getField($path, $fieldValue, $value, $field, $this);
                        }
                        if (isset($returnValue)) {
                            $attribute->value = $this->setFieldValueDot($attribute->value, $path, $returnValue);
                        }
                    });
                $value = $attribute->value;

                if ($this->isJsonCastable($key)) {
                    $value = $this->castAttributeAsJson($key, $value);
                }
            }

            $newAttributes[$key] = $value;
        }

        return $newAttributes;
    }

    /**
     * Set the array of model attributes. No checking is done.
     *
     * @param  array  $attributes
     * @param  bool  $sync
     * @return $this
     */
    public function setRawAttributes(array $attributes, $sync = false)
    {
        $preparedAttributes = $this->prepareFieldsInAttributes($attributes);
        return parent::setRawAttributes($preparedAttributes, $sync);
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param  array  $attributes
     * @return $this
     *
     * @throws \Illuminate\Database\Eloquent\MassAssignmentException
     */
    // public function fill(array $attributes)
    // {
    //     $preparedAttributes = $this->prepareFieldsInAttributes($attributes);
    //     return parent::fill($preparedAttributes);
    // }
}
