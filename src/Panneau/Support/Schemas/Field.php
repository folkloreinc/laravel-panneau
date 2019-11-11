<?php

namespace Panneau\Support\Schemas;

use Panneau\Support\Traits\SchemaPropertiesAsFields;
use Panneau\Support\Traits\SchemaHasNamespace;
use Panneau\Contracts\Support\FieldArrayable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

class Field extends Schema implements FieldArrayable
{
    use SchemaPropertiesAsFields, SchemaHasNamespace;

    protected $fieldType;

    protected $label;

    protected $fieldAttributes = [
        'fieldType',
        'label',
        'namespace'
    ];

    public static function make($name = null, $attributes = [])
    {
        if (is_array($name)) {
            $attributes = $name;
        } else {
            $attributes['name'] = $name;
        }
        return parent::make($attributes);
    }

    public function getFieldType()
    {
        $fieldType = $this->getSchemaAttribute('fieldType');
        if (!is_null($fieldType)) {
            return $fieldType;
        }

        return Str::snake(class_basename($this));
    }

    public function getLabel()
    {
        $label = $this->getSchemaAttribute('label');
        if (!is_null($label)) {
            return $label;
        }

        return array_get($this->getAttributes(), 'label', Str::title($this->getName()));
    }

    public function withLabel($label)
    {
        $this->label = $label;
        return $this;
    }

    public function getName()
    {
        $name = $this->getSchemaAttribute('name');
        if (!is_null($name)) {
            return Str::snake($name);
        }

        return array_get($this->getAttributes(), 'name');
    }

    public function withName($name)
    {
        $this->name = $name;
        return $this;
    }

    public function toFieldArray()
    {
        $fields = $this->getFields();
        $attributes = $this->getAttributes();
        return array_merge([], $attributes, [
            'name' => $this->getNameWithNamespace(),
            'type' => $this->getFieldType(),
            'label' => $this->getLabel(),
        ], !is_null($fields) ? [
            'fields' => array_map(function ($field) {
                if ($field instanceof FieldArrayable) {
                    return $field->toFieldArray();
                } elseif ($field instanceof Arrayable) {
                    return $field;
                }
                return $field;
            }, $fields),
        ] : []);
    }

    public function toValidationArray()
    {
        return [
            'required' => $this->getSchemaAttribute('required'),
            'pattern' => $this->getSchemaAttribute('pattern'),
        ];
    }

    /**
     * Dynamically call schema attributes accessors
     *
     * @param  string  $key
     * @return void
     */
    public function __call($method, $parameters)
    {
        if (preg_match('/^(get|set|with)([A-Z].*)$/i', $method, $matches)) {
            $methodAttribute = Str::snake($matches[2]);
            $foundAttribute = Arr::first($this->fieldAttributes, function ($attribute) use ($methodAttribute) {
                return $methodAttribute === Str::snake($attribute);
            });
            if (!is_null($foundAttribute)) {
                $methodPrefix = $matches[1] === 'with' ? 'get' : $matches[1];
                return call_user_func([$this, $methodPrefix.'SchemaAttribute'], $foundAttribute, ...$parameters);
            }
        }
        return parent::__call($method, $parameters);
    }
}
