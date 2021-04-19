<?php

namespace Panneau\Support;

use Panneau\Contracts\Field as FieldContract;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Closure;

abstract class Field implements FieldContract, Arrayable, Jsonable
{
    protected $name;

    protected $label;

    protected $rules;

    protected $required = false;

    protected $defaultValue;

    protected $properties;

    protected $sibblingFields;

    protected $hiddenInIndex = false;

    protected $hiddenInForm = false;

    protected $indexOrder = null;

    protected $attributes = [];

    protected $components = null;

    public static function make($name = null, $label = null)
    {
        return new static($name, $label);
    }

    public function __construct($name = null, $label = null)
    {
        $this->name = $name;
        $this->label = $label;
    }

    abstract public function type(): string;

    abstract public function component(): string;

    public function name(): string
    {
        return $this->name;
    }

    public function label(): string
    {
        return !is_null($this->label) ? $this->label : Str::title($this->name);
    }

    public function required(): bool
    {
        return $this->required;
    }

    public function properties(): ?array
    {
        return $this->properties;
    }

    public function attributes(): ?array
    {
        return $this->attributes;
    }

    public function rules(Request $request): ?array
    {
        return null;
    }

    public function components(): ?array
    {
        return $this->components;
    }

    public function sibblingFields(): ?array
    {
        return $this->sibblingFields;
    }

    public function hiddenInIndex(): bool
    {
        return $this->hiddenInIndex;
    }

    public function hiddenInForm(): bool
    {
        return $this->hiddenInForm;
    }

    public function defaultValue()
    {
        return $this->defaultValue;
    }

    public function orderInIndex(): ?int
    {
        return $this->indexOrder;
    }

    public function withName($name)
    {
        $this->name = $name;
        return $this;
    }

    public function withLabel($label)
    {
        $this->label = $label;
        return $this;
    }

    public function withTransLabel($label, $params = [])
    {
        return $this->withLabel(trans($label, $params));
    }

    public function withRules($rules)
    {
        $this->rules = $rules;
        return $this;
    }

    public function withSibblingFields($sibblingFields)
    {
        $this->sibblingFields = $sibblingFields;
        return $this;
    }

    public function withDefaultValue($defaultValue)
    {
        $this->defaultValue = $defaultValue;
        return $this;
    }

    public function withComponents($components)
    {
        $this->components = array_merge(
            !is_null($this->components) ? $this->components : [],
            $components
        );
        return $this;
    }

    public function withComponent($key, $component)
    {
        return $this->withComponents([
            $key => $component,
        ]);
    }

    public function withOrderInIndex($order)
    {
        $this->indexOrder = $order;
        return $this;
    }

    public function isRequired()
    {
        $this->required = true;
        return $this;
    }

    public function isNotRequired()
    {
        $this->required = false;
        return $this;
    }

    public function showInIndex()
    {
        $this->hiddenInIndex = false;
        return $this;
    }

    public function hideInIndex()
    {
        $this->hiddenInIndex = true;
        return $this;
    }

    public function showInForm()
    {
        $this->hiddenInForm = false;
        return $this;
    }

    public function hideInForm()
    {
        $this->hiddenInForm = true;
        return $this;
    }

    public function withProperties($properties)
    {
        $this->properties = array_merge($this->properties, $properties);
        return $this;
    }

    public function withAttributes($attributes)
    {
        $this->attributes = array_merge($this->attributes, $attributes);
        return $this;
    }

    public function getRules(Request $request): array
    {
        $computedRules = $this->rules($request);
        $rules = $this->rules;
        $propertyRules = $rules instanceof Closure ? $rules($request) : $rules;
        return array_merge(
            !is_null($computedRules) ? $computedRules : [],
            !is_null($propertyRules) ? $propertyRules : []
        );
    }

    public function toArray()
    {
        $type = $this->type();

        $data = [
            'name' => $this->name(),
            'type' => $type,
            'component' => $this->component(),
            'required' => $this->required(),
            'default_value' => $this->defaultValue(),
            'hidden_in_index' => $this->hiddenInIndex(),
            'order_in_index' => $this->orderInIndex(),
        ];

        $components = $this->components();
        if (!is_null($components)) {
            $data['components'] = $components;
        }

        $sibblingFields = $this->sibblingFields();
        if (!is_null($sibblingFields)) {
            $data['sibbling_fields'] = collect($sibblingFields)->toArray();
        }

        if ($type === 'object') {
            $data['properties'] = collect($this->properties())
                ->mapWithKeys(function ($property, $key) {
                    $property = is_string($property) ? $property::make($key) : $property;
                    return [
                        $key => $property,
                    ];
                })
                ->toArray();
        }

        $attributes = $this->attributes();
        return !is_null($attributes) ? array_merge($data, $attributes) : $data;
    }

    public function jsonSerialize()
    {
        return $this->toArray();
    }

    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }
}
