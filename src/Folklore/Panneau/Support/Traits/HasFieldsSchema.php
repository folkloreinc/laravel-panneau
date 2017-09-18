<?php

namespace Folklore\Panneau\Support\Traits;

use Folklore\Panneau\Observers\HasFieldsSchemaObserver;
use Folklore\Panneau\Validators\SchemaValidator;
use Folklore\Panneau\Exceptions\SchemaValidationException;
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

    protected $fieldsSchemaNameColumn = 'type';

    protected $fieldsAppends = [];

    protected $fieldsEnabled = [];
    protected $fieldsDisabled = [];
    protected $fieldsReducers = [];

    protected static $defaultFieldsSchema;
    protected static $defaultFieldsSchemaName = 'default';

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->appends[] = 'fieldsSchema';
    }

    public static function bootHasFieldsSchema()
    {
        static::observe(HasFieldsSchemaObserver::class);
    }

    public static function setDefaultFieldsSchema($schema)
    {
        static::$defaultFieldsSchema = $schema;
    }

    public static function getDefaultFieldsSchema()
    {
        return static::$defaultFieldsSchema;
    }

    public static function setDefaultFieldsSchemaName($name)
    {
        static::$defaultFieldsSchemaName = $name;
    }

    public static function getDefaultFieldsSchemaName()
    {
        return static::$defaultFieldsSchemaName;
    }

    public static function fieldsSchemas()
    {
        return app('panneau')->schemas(static::class);
    }

    public static function fieldsReducers()
    {
        return app('panneau')->reducers(static::class);
    }

    public static function hasFieldsSchema($name = null)
    {
        if (is_null($name)) {
            return false;
        }
        return app('panneau')->hasSchema($name, static::class);
    }

    public static function hasFieldsReducers()
    {
        return app('panneau')->hasReducers(static::class);
    }

    public static function fieldsSchema($name = null)
    {
        if (is_null($name)) {
            $defaultSchema = static::getDefaultFieldsSchema();
            if (!is_null($defaultSchema)) {
                return $defaultSchema;
            }
            $name = static::getDefaultFieldsSchemaName();
        }
        return app('panneau')->schema($name, static::class);
    }

    public static function addFieldsSchema($name, $schema)
    {
        return app('panneau')->addSchema($name, $schema, static::class);
    }

    public static function addFieldsReducer($reducer)
    {
        return app('panneau')->addReducer($reducer, static::class);
    }

    public static function addFieldsSchemas($schemas)
    {
        return app('panneau')->addSchemas($schemas, static::class);
    }

    public static function addFieldsReducers($reducers)
    {
        return app('panneau')->addReducers($reducers, static::class);
    }

    public function setFieldsSchema($schema)
    {
        $this->fieldsSchema = $schema;
    }

    public function getFieldsSchema()
    {
        if (isset($this->fieldsSchema)) {
            return $this->fieldsSchema;
        }

        $name = $this->getFieldsSchemaName();
        if (is_null($name)) {
            try {
                return static::fieldsSchema($name);
            } catch (Exception $e) {
                return null;
            }
        }
        return static::fieldsSchema($name);
    }

    public function getFieldsSchemaName()
    {
        $columnName = method_exists($this, 'getFieldsSchemaNameColumn') ?
            $this->getFieldsSchemaNameColumn() : $this->fieldsSchemaNameColumn;
        return isset($this->attributes[$columnName]) ? $this->attributes[$columnName] : null;
    }

    public function setFieldsSchemaNameColumn($column)
    {
        $this->fieldsSchemaNameColumn = $column;
    }

    public function getFieldsReducers()
    {
        $staticReducers = static::fieldsReducers(static::class);
        $instanceReducers = $this->getInstanceFieldsReducers();
        return array_merge($staticReducers, $instanceReducers);
    }

    public function setFieldsReducers($reducers)
    {
        $this->fieldsReducers = $reducers;
    }

    protected function getInstanceFieldsReducers()
    {
        $instanceReducers = $this->fieldsReducers;
        $reducers = [];
        foreach ($instanceReducers as $reducer) {
            $reducers[] = is_string($reducer) ? app($reducer) : $reducer;
        }
        return $reducers;
    }

    public function validateFieldsAgainstSchema()
    {
        $schema = $this->getFieldsSchema();
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
        $schema = $this->getFieldsSchema();
        $fields = $schema->getFieldsNames();
        foreach ($fields as $field) {
            $value = $this->getAttributeValue($field);
            $this->callFieldReducers('save', $field, $value);
        }
    }

    protected function getFieldsSchemaAttribute()
    {
        $schema = $this->getFieldsSchema();
        return $schema;
    }

    /**
    * Get the value of a field
    *
    * @param  string  $key
    * @return mixed
    */
    public function getFieldsValue()
    {
        $schema = $this->getFieldsSchema();
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
        $schema = $this->getFieldsSchema();
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
        if ($this->isFieldDisabled($key)) {
            return $state;
        }
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
        $schema = $this->getFieldsSchema();
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
        $reducersInterfaces = [
            'get' => HasReducerGetter::class,
            'set' => HasReducerSetter::class,
            'save' => HasReducerSaving::class
        ];
        if (!isset($reducersInterfaces[$mode])) {
            throw new \Exception("Unknown mode $mode");
        }
        $reducerInterface = $reducersInterfaces[$mode];
        $reducerMethod = $mode;

        $reducers = $this->getFieldsReducers();
        $schema = $this->getFieldsSchema();
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

        return array_merge(
            $attributes,
            $this->getArrayableItems($fieldsAttributes),
            $this->getArrayableItems($appendsAttributes)
        );
    }

    public function getDisabledFields()
    {
        return $this->fieldsDisabled;
    }

    public function setDisabledFields(array $disabled)
    {
        $this->fieldsDisabled = $disabled;
        return $this;
    }

    public function addDisabledField($field = null)
    {
        $this->fieldsDisabled = array_merge(
            $this->fieldsDisabled,
            is_array($field) ? $field : func_get_args()
        );
    }

    public function getEnabledFields()
    {
        return $this->fieldsEnabled;
    }

    public function setEnabledFields(array $enabled)
    {
        $this->fieldsEnabled = $enabled;
        return $this;
    }

    public function addEnabledField($field = null)
    {
        $this->fieldsEnabled = array_merge(
            $this->fieldsEnabled,
            is_array($field) ? $field : func_get_args()
        );
    }

    public function makeFieldEnabled($field)
    {
        $this->fieldsDisabled = array_diff($this->fieldsDisabled, (array) $field);
        if (! empty($this->fieldsEnabled)) {
            $this->addEnabledField($field);
        }
        return $this;
    }

    public function makeFieldDisabled($field)
    {
        $field = (array) $field;
        $this->fieldsEnabled = array_diff($this->fieldsEnabled, $field);
        $this->fieldsDisabled = array_unique(array_merge($this->fieldsDisabled, $field));
        return $this;
    }

    public function disableAllFields()
    {
        $fields = $schema->getFieldsNames();
        $this->makeFieldDisabled($fields);
    }

    public function isFieldDisabled($field)
    {
        $disabled = $this->getDisabledFields();
        return in_array($field, $disabled);
    }
}
