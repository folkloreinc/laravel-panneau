<?php

namespace Folklore\Panneau\Support\Traits;

// use Illuminate\Support\Facades\Validator;
// use Illuminate\Validation\ValidationException;
use Folklore\Panneau\Validators\JsonSchemaValidator;
use Folklore\Panneau\Exceptions\JsonSchemaException;

trait HasFieldsSchema
{
    public static function bootHasFieldsSchema()
    {
        static::observe(HasFieldsSchemaObserver::class);
    }

    public static function schemas()
    {
        return app('panneau')->schemas(static::class);
    }

    public static function schema($name)
    {
        return app('panneau')->schema($name, static::class);
    }

    public function getSchema()
    {
        if (method_exists($this, 'getSchemaName')) {
            $name = $this->getSchemaName();
        } else {
            $name = $this->type;
        }

        $schema = static::schema($name);
        $schema->setModel($model);
        return $schema;
    }

    /*protected function getSchemasForValidation()
    {
        return $this->jsonSchemas ?? null;
    }

    protected function getSchemasAppends()
    {
        return $this->jsonSchemasAppends ?? null;
    }

    protected function getSchemaByType($type = null)
    {
        $def = $this->baseSchema;

        if (is_null($type)) {
            return $this->baseSchema;
        }

        if (!isset($this->typeSchemas[$type])) {
            throw new Exception('Unknown type "'.$type.'"');
        }

        $def['data']['properties'] = array_merge($def['data']['properties'], $this->typeSchemas[$type]['properties']);
        return $def;
    }*/

    public function validateAndExtractFieldsSchemas()
    {
        $schemas = $this->getSchema();
        if (empty($schemas)) {
            return;
        }
        if (!is_array($schemas)) {
            $schemas = [$schemas];
        }

        $errorsList = [];
        foreach ($schemas as $field => $schema) {
            if (!isset($this->{$field})) {
                $obj = new \stdClass();
            } else {
                $obj = $this->{$field};
            }
            foreach ($schema['properties'] as $prop => $value) {
                if (isset($this->attributes[$prop])) {
                    $obj->{$prop} = $this->attributes[$prop];
                }
                unset($this->{$prop});
            }
            $this->{$field} = $obj;

            $errors = JsonSchemaValidator::validateAgainstSchema($schema, $this->{$field});
            if ($errors) {
                $errorsList += $errors;
            }
        }

        if (!empty($errorsList)) {
            throw new JsonSchemaException($errorsList);
        }
    }

    public function __call($name, $arguments)
    {
        $schemas = $this->getSchemasForValidation();
        if (!empty($schemas)) {
            foreach ($schemas as $field => $schema) {
                foreach ($schema['properties'] as $prop => $value) {
                    if ('get'.ucfirst($prop).'Attribute' === $name) {
                        return isset($this->{$field}->{$prop}) ? $this->{$field}->{$prop} : null;
                    } elseif ('set'.ucfirst($prop).'Attribute' === $name) {
                        $this->{$field}->{$prop} = array_get($arguments, 0, null);
                        return;
                    }
                }
            }
        }
        return parent::__call($name, $arguments);
    }

    public function toArray()
    {
        $data = parent::toArray();
        $appends = $this->getSchemasAppends();
        if (!empty($appends)) {
            foreach ($appends as $prop => $append) {
                $append = explode('.', $append);
                if (isset($this->{$append[0]}->{$append[1]})) {
                    $data[$prop] = $this->{$append[0]}->{$append[1]};
                }
            }
        }
        return $data;
    }
}
