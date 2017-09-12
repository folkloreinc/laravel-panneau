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

    public function validateFieldsAgainstSchema()
    {
        $schema = $this->getSchema();
        if (is_null($schema)) {
            return;
        }
        $data = $this->getFieldsValue()->toObject();
        $validator = app(\Folklore\Panneau\Contracts\SchemaValidator::class);
        if (!$validator->validateSchema($data, $schema)) {
            throw new SchemaValidationException($validator->getMessages());
        }
    }

    public function saveFields()
    {
        $schema = $this->getSchema();
        $fields = $schema->getFieldsNames();
        foreach ($fields as $field) {
            $value = $this->getFieldValue($field);
            $this->callFieldReducers('save', $field, $value);
        }
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
            $fieldValue = $this->getFieldValue($field);
            $value[$field] = !is_null($fieldValue) ? new FieldValue($fieldValue) : null;
        }
        return new FieldValue($value);
    }


    /**
     * Get the fields to Array
     *
     * @param  array  $attributes
     * @return FieldValue  $fieldsAttributes
     */
    public function fieldsToArray()
    {
        $fieldsValue = $this->getFieldsValue();
        return $fieldsValue->toArray();
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
        return !is_null($path) ? $this->getFieldsValue()->get($path) : null;
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
        }/* elseif ($this->attributeIsFieldAppend($key)) {
            return $this->getFieldAppendValue($key);
        }*/
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
        // Here we prepend the field's name to the computed paths, so that we still get
        // a full node path in the reducers while scoping the node list to only the current
        // field. See also the $data array below.
        $nodesCollection = $schema->getNodesFromData($state, $name)->prependPath($name);
        $data = [];
        $data[$name] = is_object($state) ? clone $state : $state;
        $data = $nodesCollection->reduce(function ($state, $node) use ($reducers, $reducerInterface, $reducerMethod) {
            foreach ($reducers as $reducer) {
                if ($reducer instanceof $reducerInterface) {
                    $state = $reducer->{$reducerMethod}($this, $node, $state);
                } elseif (is_callable($reducer)) {
                    $state = call_user_func_array($reducer, [$this, $node, $state]);
                }
            }
            return $state;
        }, $data);
        return $data[$name];
    }

    /**
     * Convert the model's attributes to an array.
     *
     * @return array
     */
    public function attributesToArray()
    {
        $attributes = parent::attributesToArray();

        $fieldsAttributes = [];
        $appendsAttributes = [];
        $fieldsAttributes = $this->fieldsToArray();
        foreach ($this->getFieldsAppends() as $key => $path) {
            if (is_numeric($key)) {
                $appendsAttributes[$path] = array_get($fieldsAttributes, $path);
            } else {
                $appendsAttributes[$key] = array_get($fieldsAttributes, $path);
            }
        }

        return array_merge($attributes, $fieldsAttributes, $appendsAttributes);
    }
}
