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

    protected $disabled = false;

    protected $defaultValue;

    protected $properties;

    protected $siblingFields;

    protected $components = null;

    protected $exceptTypes = null;

    protected $onlyTypes = null;

    protected $attributes = [];

    protected $settings = [
        'hiddenInForm' => false,
        'createOnly' => false,
        'updateOnly' => false,
    ];

    public static function make($name = null, $label = null)
    {
        return new static($name, $label);
    }

    public function __construct($name = null, $label = null)
    {
        $this->name = $name;
        $this->label = $label;
    }

    public function name(): ?string
    {
        return $this->name;
    }

    public function label(): string
    {
        return !is_null($this->label) ? $this->label : Str::title($this->name);
    }

    abstract public function type(): string;

    abstract public function component(): string;

    public function required(): bool
    {
        return $this->required;
    }

    public function disabled(): bool
    {
        return $this->disabled;
    }

    public function defaultValue()
    {
        return $this->defaultValue;
    }

    public function properties(): ?array
    {
        return $this->properties;
    }

    public function attributes(): ?array
    {
        return $this->attributes;
    }

    public function settings(): ?array
    {
        return $this->settings;
    }

    public function components(): ?array
    {
        return $this->components;
    }

    public function siblingFields(): ?array
    {
        return $this->siblingFields;
    }

    public function exceptTypes(): ?array
    {
        return $this->exceptTypes;
    }

    public function onlyTypes(): ?array
    {
        return $this->onlyTypes;
    }

    public function rules(Request $request): ?array
    {
        return null;
    }

    public function getRulesFromRequest(Request $request): array
    {
        $computedRules = $this->rules($request);
        $rules = $this->rules;
        $propertyRules = $rules instanceof Closure ? $rules($request) : $rules;
        return array_merge(
            !is_null($computedRules) ? $computedRules : [],
            !is_null($propertyRules) ? $propertyRules : []
        );
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

    public function withSiblingFields($fields)
    {
        $this->siblingFields = is_array($fields) ? $fields : func_get_args();
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

    public function onlyForTypes($types)
    {
        $this->onlyTypes = collect($this->onlyTypes ?? [])
            ->merge(is_array($types) ? $types : func_get_args())
            ->unique()
            ->values()
            ->toArray();
        return $this;
    }

    public function exceptForTypes($types)
    {
        $this->exceptTypes = collect($this->exceptTypes ?? [])
            ->merge(is_array($types) ? $types : func_get_args())
            ->unique()
            ->values()
            ->toArray();
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

    public function isDisabled()
    {
        $this->disabled = true;
        return $this;
    }

    public function isEnabled()
    {
        $this->disabled = false;
        return $this;
    }

    public function showInForm()
    {
        $this->settings['hiddenInForm'] = false;
        return $this;
    }

    public function hideInForm()
    {
        $this->settings['hiddenInForm'] = true;
        return $this;
    }

    public function createOnly()
    {
        $this->settings['createOnly'] = true;
        return $this;
    }

    public function updateOnly()
    {
        $this->settings['updateOnly'] = true;
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

    public function withSettings($settings)
    {
        $this->settings = array_merge($this->settings, $settings);
        return $this;
    }

    public function toArray()
    {
        $type = $this->type();

        $data = [
            'label' => $this->label(),
            'type' => $type,
            'component' => $this->component(),
            'required' => $this->required(),
            'disabled' => $this->disabled(),
            'defaultValue' => $this->defaultValue(),
            'settings' => $this->settings,
        ];

        $components = $this->components();
        if (!is_null($components)) {
            $data['components'] = $components;
        }

        $name = $this->name();
        if (!is_null($name)) {
            $data['name'] = $name;
        }

        $siblingFields = $this->siblingFields();
        if (!is_null($siblingFields)) {
            $data['siblingFields'] = collect($siblingFields)->toArray();
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
