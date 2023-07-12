<?php

namespace Panneau\Support;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use Panneau\Contracts\Form as FormContract;

abstract class Form implements FormContract, Arrayable
{
    public static $components;

    public static $settings = [];

    public static $attributes = [];

    private static $defaultSettings = [];

    public function id(): string
    {
        return Str::camel(preg_replace('/Form$/', '', class_basename(get_class($this))));
    }

    public function name(): string
    {
        return preg_replace('/Form$/', '', class_basename(get_class($this)));
    }

    abstract public function fields(): array;

    public function components(): ?array
    {
        return static::$components;
    }

    public function settings(): ?array
    {
        return array_merge(self::$defaultSettings, static::$settings);
    }

    public function attributes(): ?array
    {
        return static::$attributes;
    }

    public function toArray()
    {
        $data = [
            'id' => $this->id(),
            'name' => $this->name(),
            'fields' => collect($this->fields())->toArray(),
        ];

        $settings = $this->settings();
        if (isset($settings)) {
            $data['settings'] = $settings;
        }

        $components = $this->components();
        if (isset($components)) {
            $data['components'] = $components;
        }

        $attributes = $this->attributes();
        if (isset($attributes) && !is_null($attributes)) {
            return array_merge($data, $attributes);
        }

        return $data;
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
