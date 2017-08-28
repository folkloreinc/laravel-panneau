<?php

namespace Folklore\Panneau\Support\Traits;

use Folklore\Panneau\Observers\HasFieldsSchemaObserver;
use Folklore\Panneau\Validators\SchemaValidator;
use Folklore\Panneau\Exceptions\SchemaValidationException;
use Folklore\Panneau\Support\FieldsCollection;
use Folklore\Panneau\Support\FieldValue;
use Folklore\Panneau\Support\Utils;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;
use StdClass;

trait HasFieldsSchema
{
    protected $fieldsSchema;

    protected $schemaNameColumn = 'type';

    protected $originalFieldValue = [];

    protected $fieldsAttributes = [];

    protected static $defaultSchema;

    protected static $defaultSchemaName = 'default';

    public static function bootHasFieldsSchema()
    {
        static::observe(HasFieldsSchemaObserver::class);
    }

    public static function setDefaultSchema($schema)
    {
        static::$defaultSchema = $schema;
    }

    public static function getDefaultSchema()
    {
        return static::$defaultSchema;
    }

    public static function setDefaultSchemaName($name)
    {
        static::$defaultSchemaName = $name;
    }

    public static function getDefaultSchemaName()
    {
        return static::$defaultSchemaName;
    }

    public static function schemas()
    {
        return app('panneau')->schemas(static::class);
    }

    public static function hasSchema($name = null)
    {
        if (is_null($name)) {
            return false;
        }
        return app('panneau')->hasSchema($name, static::class);
    }

    public static function schema($name = null)
    {
        if (is_null($name)) {
            $defaultSchema = static::getDefaultSchema();
            if (!is_null($defaultSchema)) {
                return $defaultSchema;
            }
            $name = static::getDefaultSchemaName();
        }
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
        return isset($this->attributes[$columnName]) ? $this->attributes[$columnName] : null;
    }

    public function getSchema()
    {
        if (isset($this->fieldsSchema)) {
            return $this->fieldsSchema;
        }

        $name = $this->getSchemaName();
        $defaultSchemaName = static::getDefaultSchemaName();
        $defaultSchema = static::getDefaultSchema();
        if (is_null($name) && !is_null($defaultSchema)) {
            return $defaultSchema;
        } elseif (is_null($name) && static::hasSchema($defaultSchemaName)) {
            $name = $defaultSchemaName;
        }

        return static::hasSchema($name) ? static::schema($name) : null;
    }

    public function setSchema($schema)
    {
        $this->fieldsSchema = $schema;
    }

    public function setSchemaNameColumn($column)
    {
        $this->schemaNameColumn = $column;
    }

    public function attributeHasSchema($key)
    {
        $schema = $this->getSchema();
        if (is_null($schema)) {
            return false;
        }
        $fields = $schema->getFieldsNames();
        return in_array($key, $fields);
    }

    protected function getFieldPathValue($path, $data = null)
    {
        $data = !is_null($data) ? $data : $this->fieldsAttributes;
        return Utils::getPath($data, $path);
    }

    protected function setFieldPathValue($key, $value)
    {
        $keyParts = explode('.', $key);
        if (sizeof($keyParts) === 1) {
            $this->{$field} = $value;
            return;
        }

        $field = array_shift($keyParts);
        $this->fieldsAttributes[$field]->set(implode('.', $keyParts), $value);
        //$this->{$field} = Utils::setPath($this->{$field}, implode('.', $keyParts), $value);
        return $this;
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

    public function validateFieldsAgainstSchema()
    {
        $schema = $this->getSchema();
        $fields = $schema->getFieldsNames();
        $data = new StdClass();
        foreach ($fields as $field) {
            if ($this->hasCast($field) &&
                $this->getCastType($field) === 'object' &&
                isset($this->{$field}) && is_array($this->{$field})
            ) {
                $data->{$field} = (object)$this->{$field};
            } elseif (isset($this->{$field})) {
                $data->{$field} = $this->{$field};
            }
        }
        $data = json_decode(json_encode($data));

        $validator = app(\Folklore\Panneau\Contracts\SchemaValidator::class);
        if (!$validator->validateSchema($data, $schema)) {
            throw new SchemaValidationException($validator->getMessages());
        }
    }

    public function prepareFieldsForSaving()
    {
        $this->fieldsCollection()
            ->eachPath(function ($path, $key, $field) {
                $schema = $field->schema;
                $value = $this->getFieldPathValue($path);
                $prepareMethod = 'prepare'.studly_case($field->type).'Field';
                if (method_exists($this, $prepareMethod)) {
                    $returnValue = $this->{$prepareMethod}($path, $value, $field);
                } elseif (is_object($schema) && method_exists($schema, 'prepareField')) {
                    $returnValue = $schema->prepareField($path, $value, $field, $this);
                }
                if (isset($returnValue)) {
                    $this->setFieldOriginalValue($path, $value);
                    $this->setFieldPathValue($path, $returnValue);
                }
            });

        foreach ($this->fieldsAttributes as $key => $value) {
            if ($this->isJsonCastable($key)) {
                $this->attributes[$key] = $value->toJSON();
            } else {
                $this->attributes[$key] = $value->getValue();
            }
        }
    }

    public function saveFields()
    {
        $this->fieldsCollection()
            ->eachPath(function ($path, $key, $field) {
                $schema = $field->schema;
                $value = $this->getFieldPathValue($path);
                $originalValue = $this->getFieldOriginalValue($path);
                $saveMethod = 'save'.studly_case($field->type).'Field';
                if (method_exists($this, $saveMethod)) {
                    $returnValue = $this->{$saveMethod}($path, $value, $originalValue, $field);
                } elseif (is_object($schema) && method_exists($schema, 'saveField')) {
                    $returnValue = $schema->saveField($path, $value, $originalValue, $field, $this);
                }
            });

        $this->clearFieldOriginalValue();
        $this->fieldsAttributes = $this->getFieldsFromAttributes($this->attributes);
    }

    /**
     * Get the fields in attributes according to the schema and accessors methods
     *
     * @param  array  $attributes
     * @return array  $attributes
     */
    public function getFieldsFromAttributes(array $attributes)
    {
        $newAttributes = [];
        foreach ($attributes as $key => $value) {
            if (!$this->attributeHasSchema($key)) {
                continue;
            }

            if ($this->isJsonCastable($key)) {
                $value = $this->castAttribute($key, $value);
            }

            $fieldValue = new FieldValue($value);

            // $attribute = new StdClass();
            // $attribute->key = $key;
            // $attribute->value = $value;
            $this->fieldsCollection($key, $value)
                // ->eachPath(function ($path, $key, $field) use ($attribute) {
                ->eachPath(function ($path, $key, $field) use ($key, $fieldValue) {
                    // $fullPath = $attribute->key.'.'.$path;
                    // $value = $attribute->value;
                    $fullPath = $key.'.'.$path;
                    $schema = $field->schema;
                    //$fieldValue = $this->getFieldPathValue($path, $value);
                    $value = $fieldValue->get($path);
                    $getMethod = 'get'.studly_case($field->type).'Field';
                    if (method_exists($this, $getMethod)) {
                        $returnValue = $this->{$getMethod}($fullPath, $value, $fieldValue, $field);
                    } elseif (is_object($schema) && method_exists($schema, 'getField')) {
                        $returnValue = $schema->getField($fullPath, $value, $fieldValue, $field, $this);
                    }
                    if (isset($returnValue)) {
                        $fieldValue->set($path, $returnValue);
                        //$attribute->value = Utils::setPath($attribute->value, $path, $returnValue);
                    }
                });

            // if ($this->isJsonCastable($key)) {
            //     $value = $this->castAttributeAsJson($key, $value);
            // }

            $newAttributes[$key] = $fieldValue;
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
        $return = parent::setRawAttributes($attributes, $sync);
        $this->fieldsAttributes = $this->getFieldsFromAttributes($attributes);
        return $return;
    }

    /**
     * Get an attribute from the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        if ($this->attributeHasSchema($key)) {
            return $this->getFieldValue($key);
        }
        return parent::getAttribute($key);
    }

    public function getFieldValue($key)
    {
        if (isset($this->fieldsAttributes[$key])) {
            return $this->fieldsAttributes[$key];
        }
    }

    /**
     * Determine if a get mutator exists for an attribute.
     *
     * @param  string  $key
     * @return bool
     */
    // public function hasGetMutator($key)
    // {
    //     return parent::hasGetMutator($key) || $this->attributeHasSchema($key);
    // }

    /**
     * Get the value of an attribute using its mutator.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    // protected function mutateAttribute($key, $value)
    // {
    //     if (parent::hasGetMutator($key)) {
    //         $value = parent::mutateAttribute($key, $value);
    //     }
    //     if ($this->attributeHasSchema($key)) {
    //         $value = array_get($this->fieldsAttributes, $key);
    //     }
    //     return $value;
    // }

    /**
     * Set a given attribute on the model.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return $this
     */
    public function setAttribute($key, $value)
    {
        if ($this->attributeHasSchema($key)) {
            array_set($this->fieldsAttributes, $key, new FieldValue($value));
        }
        return parent::setAttribute($key, $value);
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
