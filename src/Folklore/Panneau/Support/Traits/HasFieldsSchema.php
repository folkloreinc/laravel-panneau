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
use Exception;

trait HasFieldsSchema
{
    protected $fieldsSchema;

    protected $schemaNameColumn = 'type';

    protected $fieldsAttributes;

    protected $fieldsAttributesForSaving;

    protected $fieldsAppends = [];

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

    public function __construct(array $attributes = [])
    {
        $this->fieldsAttributes = new FieldValue();
        parent::__construct($attributes);
        $this->appends[] = 'fieldsSchema';
    }

    protected function getFieldsSchemaAttribute()
    {
        $schema = $this->getSchema();
        return !is_null($schema) ? $schema->toArray() : null;
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
        if (is_null($name)) {
            try {
                return static::schema($name);
            } catch (Exception $e) {
                return null;
            }
        }
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
        if (is_null($schema)) {
            return;
        }
        $data = $this->fieldsAttributes->toObject();
        $validator = app(\Folklore\Panneau\Contracts\SchemaValidator::class);
        if (!$validator->validateSchema($data, $schema)) {
            throw new SchemaValidationException($validator->getMessages());
        }
    }

    public function prepareFieldsForSaving()
    {
        $this->fieldsAttributesForSaving = $this->fieldsAttributes->clone();

        $this->fieldsCollection()
            ->eachPath(function ($path, $key, $field) {
                $schema = $field->schema;
                $value = $this->fieldsAttributesForSaving->get($path);
                $prepareMethod = 'prepare'.studly_case($field->type).'Field';
                if (method_exists($this, $prepareMethod)) {
                    $returnValue = $this->{$prepareMethod}($path, $value, $field);
                } elseif (is_object($schema) && method_exists($schema, 'prepareField')) {
                    $returnValue = $schema->prepareField($path, $value, $field, $this);
                }
                if (isset($returnValue)) {
                    $this->fieldsAttributesForSaving->set($path, $returnValue);
                }
            });

        $fieldsValue = $this->fieldsAttributesForSaving->getValue();
        foreach ($fieldsValue as $key => $value) {
            if ($this->isJsonCastable($key)) {
                $this->attributes[$key] = json_encode($value);
            } else {
                $this->attributes[$key] = $value instanceof FieldValue ? $value->getValue() : $value;
            }
        }
    }

    public function saveFields()
    {
        $this->fieldsCollection()
            ->eachPath(function ($path, $key, $field) {
                $schema = $field->schema;
                $value = $this->fieldsAttributesForSaving->get($path);
                $originalValue = $this->fieldsAttributes->get($path);

                $saveMethod = 'save'.studly_case($field->type).'Field';
                if (method_exists($this, $saveMethod)) {
                    $returnValue = $this->{$saveMethod}($path, $value, $originalValue, $field);
                } elseif (is_object($schema) && method_exists($schema, 'saveField')) {
                    $returnValue = $schema->saveField($path, $value, $originalValue, $field, $this);
                }
            });

        $this->fieldsAttributesForSaving = null;
        $this->fieldsAttributes = $this->getFieldsFromAttributes($this->attributes);
    }

    /**
     * Get the fields in attributes according to the schema and accessors methods
     *
     * @param  array  $attributes
     * @return FieldValue  $fieldsAttributes
     */
    public function getFieldsFromAttributes(array $attributes)
    {
        $fieldsAttributes = new FieldValue();
        foreach ($attributes as $attributeKey => $attributeValue) {
            if (!$this->attributeIsField($attributeKey)) {
                continue;
            }

            if ($this->isJsonCastable($attributeKey)) {
                $attributeValue = $this->castAttribute($attributeKey, $attributeValue);
            }

            $fieldValue = new FieldValue($attributeValue);

            $this->fieldsCollection($attributeKey, $attributeValue)
                ->eachPath(function ($path, $key, $field) use ($attributeKey, $fieldValue) {
                    $fullPath = $attributeKey.'.'.$path;
                    $schema = $field->schema;
                    $value = $fieldValue->get($path);
                    $getMethod = 'get'.studly_case($field->type).'Field';
                    if (method_exists($this, $getMethod)) {
                        $returnValue = $this->{$getMethod}($fullPath, $value, $fieldValue, $field);
                    } elseif (is_object($schema) && method_exists($schema, 'getField')) {
                        $returnValue = $schema->getField($fullPath, $value, $fieldValue, $field, $this);
                    }
                    if (isset($returnValue)) {
                        $fieldValue->set($path, $returnValue);
                    }
                });

            $fieldsAttributes->set($attributeKey, $fieldValue);
        }

        return $fieldsAttributes;
    }

    public function attributeIsField($key)
    {
        $schema = $this->getSchema();
        if (is_null($schema)) {
            return false;
        }
        return $schema->hasField($key);
    }

    public function attributeIsFieldAppend($key)
    {
        $appends = $this->getFieldsAppends();
        foreach ($appends as $appendKey => $appendPath) {
            if ((is_numeric($appendKey) && $appendPath === $key) || $appendKey === $key) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get the value of a field
     *
     * @param  string  $key
     * @return mixed
     */
    public function getFieldValue($key)
    {
        if (isset($this->fieldsAttributes[$key])) {
            return $this->fieldsAttributes[$key];
        }
    }

    /**
     * Get the value of a field
     *
     * @param  string  $key
     * @return mixed
     */
    public function getFieldAppendValue($key)
    {
        $appends = $this->getFieldsAppends();
        $path = null;
        foreach ($appends as $appendKey => $appendPath) {
            if ((is_numeric($appendKey) && $appendPath === $key) || $appendKey === $key) {
                $path = $appendPath;
                break;
            }
        }
        return !is_null($path) ? $this->fieldsAttributes->get($path) : null;
    }

    /**
     * Get the fields appends
     *
     * @return array
     */
    public function getFieldsAppends()
    {
        if (method_exists($this, 'fieldsAppends')) {
            return $this->fieldsAppends();
        } elseif (sizeof($this->fieldsAppends)) {
            return $this->fieldsAppends;
        }
        $schema = $this->getSchema();
        return !is_null($schema) ? $schema->getAppends() : [];
    }

    /**
     * Set the fields appends
     *
     * @param  array  $appends
     * @return $this
     */
    public function setFieldsAppends($appends)
    {
        $this->fieldsAppends = $appends;
        return $this;
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
        if ($this->attributeIsField($key)) {
            return $this->getFieldValue($key);
        } elseif ($this->attributeIsFieldAppend($key)) {
            return $this->getFieldAppendValue($key);
        }
        return parent::getAttribute($key);
    }

    /**
     * Set a given attribute on the model.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return $this
     */
    public function setAttribute($key, $value)
    {
        if ($this->attributeIsField($key)) {
            $this->fieldsAttributes->set($key, new FieldValue($value));
            return $this;
        }
        return parent::setAttribute($key, $value);
    }

    /**
     * Convert the model's attributes to an array.
     *
     * @return array
     */
    public function attributesToArray()
    {
        $attributes = parent::attributesToArray();

        $appendsAttributes = [];
        foreach ($this->getFieldsAppends() as $key => $path) {
            if (is_numeric($key)) {
                $appendsAttributes[$path] = $this->fieldsAttributes->get($path);
            } else {
                $appendsAttributes[$key] = $this->fieldsAttributes->get($path);
            }
        }

        return array_merge($attributes, $this->fieldsAttributes->toArray(), $appendsAttributes);
    }
}
