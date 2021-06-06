<?php

namespace Panneau\Support;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Panneau\Contracts\Resource as ResourceContract;
use Panneau\Contracts\Intl as IntlContract;

class ResourceIntl implements IntlContract, Arrayable
{
    protected $resource;

    protected $translator;

    public function __construct(ResourceContract $resource, Translator $translator)
    {
        $this->resource = $resource;
        $this->translator = $translator;
    }

    public function values(): ?array
    {
        $namespace = $this->resource->translationsNamespace();
        $valuesKey = $namespace . '.values';
        $id = $this->resource->id();
        $resourceName = $this->resource->name();
        $name = $this->translator->has($valuesKey . '.name')
            ? $this->translator->get($valuesKey . '.name')
            : $resourceName;
        $singularName = Str::lower(Str::singular($name));
        $pluralName = Str::lower(Str::plural($name));
        $plural = $this->translator->has($valuesKey . '.plural')
            ? $this->translator->get($valuesKey . '.plural')
            : $pluralName;
        $singular = $this->translator->has($valuesKey . '.singular')
            ? $this->translator->get($valuesKey . '.singular')
            : $singularName;
        return [
            'name' => $name,
            'plural' => $this->translator->has($valuesKey . '.plural')
                ? $this->translator->get($valuesKey . '.plural')
                : $plural,
            'Plural' => $this->translator->has($valuesKey . '.Plural')
                ? $this->translator->get($valuesKey . '.Plural')
                : Str::title($plural),
            'singular' => $this->translator->has($valuesKey . '.singular')
                ? $this->translator->get($valuesKey . '.singular')
                : $singularName,
            'Singular' => $this->translator->has($valuesKey . '.Singular')
                ? $this->translator->get($valuesKey . '.Singular')
                : Str::title($singularName),
            'a_singular' => $this->translator->has($valuesKey . '.a_singular')
                ? $this->translator->get($valuesKey . '.a_singular')
                : $this->translator->get('panneau::resources.a_singular', [
                    'resource' => $singular,
                ]),
            'A_singular' => $this->translator->has($valuesKey . '.A_singular')
                ? $this->translator->get($valuesKey . '.A_singular')
                : $this->translator->get('panneau::resources.A_singular', [
                    'resource' => $singular,
                ]),
            'the_singular' => $this->translator->has($valuesKey . '.the_singular')
                ? $this->translator->get($valuesKey . '.the_singular')
                : $this->translator->get('panneau::resources.the_singular', [
                    'resource' => $singular,
                ]),
            'The_singular' => $this->translator->has($valuesKey . '.The_singular')
                ? $this->translator->get($valuesKey . '.The_singular')
                : $this->translator->get('panneau::resources.The_singular', [
                    'resource' => $singular,
                ]),
        ];
    }

    public function messages(): ?array
    {
        $namespace = $this->resource->translationsNamespace();
        return !is_null($namespace) &&
            $this->translator->has($namespace) &&
            is_array($this->translator->get($namespace))
            ? Arr::except($this->translator->get($namespace), ['values'])
            : null;
    }

    public function toArray()
    {
        $data = [];
        $values = $this->values();
        if (isset($values)) {
            $data['values'] = $values;
        }
        $messages = $this->messages();
        if (isset($messages)) {
            $data['messages'] = $messages;
        }
        return sizeof($data) > 0 ? $data : null;
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
