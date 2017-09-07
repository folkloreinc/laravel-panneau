<?php

namespace Folklore\Panneau\Support\Traits;

use Folklore\Panneau\Observers\HasFieldsSchemaObserver;
use Folklore\Panneau\Validators\SchemaValidator;
use Folklore\Panneau\Exceptions\SchemaValidationException;
use Folklore\Panneau\Support\FieldsCollection;
use Folklore\Panneau\Support\FieldValue;
use Folklore\Panneau\Support\Utils;
use Folklore\Panneau\Support\Interfaces\HasReducerGetter;
use Folklore\Panneau\Support\Interfaces\HasReducerSetter;
use Folklore\Panneau\Support\Interfaces\HasReducerSaving;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;
use StdClass;
use Exception;

trait HasFieldsSchema
{
    protected $fieldsSchema;

    protected $schemaNameColumn = 'type';

    protected $fieldsAppends = [];

    protected static $defaultSchema;

    protected static $defaultSchemaName = 'default';

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->appends[] = 'fieldsSchema';
    }

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

    public function setSchema($schema)
    {
        $this->fieldsSchema = $schema;
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

    public function getSchemaName()
    {
        $columnName = method_exists($this, 'getSchemaNameColumn') ?
            $this->getSchemaNameColumn() : $this->schemaNameColumn;
        return isset($this->attributes[$columnName]) ? $this->attributes[$columnName] : null;
    }

    public function setSchemaNameColumn($column)
    {
        $this->schemaNameColumn = $column;
    }

    public static function schemas()
    {
        return app('panneau')->schemas(static::class);
    }

    public static function reducers()
    {
        return app('panneau')->reducers(static::class);
    }

    public static function hasSchema($name = null)
    {
        if (is_null($name)) {
            return false;
        }
        return app('panneau')->hasSchema($name, static::class);
    }

    public static function hasReducers()
    {
        return app('panneau')->hasReducers(static::class);
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

    public static function addReducer($reducer)
    {
        return app('panneau')->addReducer($reducer, static::class);
    }

    public static function addSchemas($schemas)
    {
        return app('panneau')->addSchemas($schemas, static::class);
    }

    public static function addReducers($reducers)
    {
        return app('panneau')->addReducers($reducers, static::class);
    }

    protected function getFieldsSchemaAttribute()
    {
        $schema = $this->getSchema();
        return !is_null($schema) ? $schema->toArray() : null;
    }

    public function getReducers()
    {
        return static::reducers(static::class);
    }

    protected function fieldsCollection($key = null, $data = null)
    {
        $schema = $this->getSchema();
        $nodes = $schema->getNodes();
        $nodesFromData = $nodes->makeFromData($data);

        $fields = new FieldsCollection();
        foreach ($nodes as $path => $field) {
            $pathParts = explode('.', $path);
            if (!is_null($key) && !array_shift($pathParts) === $key) {
                continue;
            }
            $path = implode('.', $pathParts);
            $field = new Fluent($field);
            $field->path = $path;
            $field->paths = $nodesFromData; // @TODO change $nodesFromData, testing
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
        $data = (object)$this->getFieldsValue();
        $validator = app(\Folklore\Panneau\Contracts\SchemaValidator::class);
        if (!$validator->validateSchema($data, $schema)) {
            throw new SchemaValidationException($validator->getMessages());
        }
    }

    public function prepareFieldsForSaving()
    {
        $fieldsAttributes = $this->fieldsAttributes();
        $this->fieldsAttributesForSaving = clone $fieldsAttributes;

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
        $schema = $this->getSchema();
        $fields = $schema->getFieldsNames();
        foreach ($fields as $field) {
            $value = $this->{$field};
            $this->callFieldReducers('save', $field, $value);
        }
        /*$this->fieldsCollection()
            ->eachPath(function ($path, $key, $field) {
                $schema = $field->schema;
                $value = $this->fieldsAttributesForSaving->get($path);
                $originalValue = $this->fieldsAttributes()->get($path);

                $saveMethod = 'save'.studly_case($field->type).'Field';
                if (method_exists($this, $saveMethod)) {
                    $returnValue = $this->{$saveMethod}($path, $value, $originalValue, $field);
                } elseif (is_object($schema) && method_exists($schema, 'saveField')) {
                    $returnValue = $schema->saveField($path, $value, $originalValue, $field, $this);
                }
            });

        $this->fieldsAttributesForSaving = null;
        $this->fieldsAttributes = $this->getFieldsFromAttributes($this->attributes);*/
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
                ->eachPath(function ($node, $key, $field) use ($attributeKey, $fieldValue) {
                    $fullPath = $attributeKey.'.'.$node->path;
                    $schema = $field->schema;
                    $value = $fieldValue->get($node->path);
                    $getMethod = 'get'.studly_case($field->type).'Field';
                    if (method_exists($this, $getMethod)) {
                        $returnValue = $this->{$getMethod}($fullPath, $value, $fieldValue, $field);
                    } elseif (is_object($schema) && method_exists($schema, 'getField')) {
                        $returnValue = $schema->getField($fullPath, $value, $fieldValue, $field, $this);
                    }
                    if (isset($returnValue)) {
                        $fieldValue->set($node->path, $returnValue);
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
    public function getFieldsValue()
    {
        $schema = $this->getSchema();
        $fields = $schema->getFieldsNames();
        $value = [];
        foreach ($fields as $field) {
            $value[$field] = $this->getFieldValue($field);
        }
        return $value;
    }

    /**
     * Get the value of a field
     *
     * @param  string  $key
     * @return mixed
     */
    public function getFieldValue($key)
    {
        $state = $this->getAttributeValue($key);
        return $this->callFieldReducers('get', $key, $state);
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
        return !is_null($path) ? $this->getFieldsFromAttributes($this->attributes)->get($path) : null;
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
            $value = $this->callFieldReducers('set', $key, $value);
        }
        return parent::setAttribute($key, $value);
    }

    /**
     * Apply all reducers on a given attribute.
     *
     * @param  string $mode
     * @param  string $name
     * @param  mixed $state
     * @return mixed
     */
    protected function callFieldReducers($mode, $name, $state)
    {
        $reducerInterface = null;
        $reducerMethod = null;
        switch ($mode) {
            case 'get':
                $reducerInterface = HasReducerGetter::class;
                $reducerMethod = 'get';
                break;
            case 'set':
                $reducerInterface = HasReducerSetter::class;
                $reducerMethod = 'set';
                break;
            case 'save':
                $reducerInterface = HasReducerSaving::class;
                $reducerMethod = 'save';
                break;
            default:
                throw new \Exception("Unknown mode $mode");
                break;
        }
        $reducers = $this->getReducers();
        $schema = $this->getSchema();
        $data = [];
        $data[$name] = $state;
        $nodesCollection = $schema->getNodes()->makeFromData($data)->map(function ($node) use ($name) {
            $node->path = preg_replace('/^'.$name.'(\.(.+))?$/', '$2', $node->path);
            return $node;
        });
        return $nodesCollection->reduce(function ($state, $node) use ($reducers, $reducerInterface, $reducerMethod) {
            foreach ($reducers as $reducer) {
                if ($reducer instanceof $reducerInterface) {
                    $state = $reducer->{$reducerMethod}($this, $node, $state);
                } elseif (is_callable($reducer)) {
                    $state = call_user_func_array($reducer, [$this, $node, $state]);
                }
            }
            return $state;
        }, $state);
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
                $appendsAttributes[$path] = $this->getFieldsFromAttributes($this->attributes)->get($path);
            } else {
                $appendsAttributes[$key] = $this->getFieldsFromAttributes($this->attributes)->get($path);
            }
        }

        // @TODO Remove toArray(true) and do a special case only when json
        // null column in db results in empty array; we want an empty object
        return array_merge($attributes, $this->getFieldsFromAttributes($this->attributes)->toArray(true), $appendsAttributes);
    }
}
