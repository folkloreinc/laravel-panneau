<?php

namespace Panneau\Support;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Str;
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
        $id = $this->resource->id();
        $name = $this->resource->name();
        $singularName = Str::lower(Str::singular($name));
        $pluralName = Str::lower(Str::plural($name));
        $plural = $this->translator->has('panneau::resources.' . $id . '_plural')
            ? $this->translator->get('panneau::resources.' . $id . '_plural')
            : $pluralName;
        $singular = $this->translator->has('panneau::resources.' . $id . '_singular')
            ? $this->translator->get('panneau::resources.' . $id . '_singular')
            : $singularName;
        return [
            'name' => $name,
            'plural' => $plural,
            'Plural' => $this->translator->has('panneau::resources.' . $id . '_Plural')
                ? $this->translator->get('panneau::resources.' . $id . '_Plural')
                : Str::title($plural),
            'singular' => $singularName,
            'Singular' => $this->translator->has('panneau::resources.' . $id . '_Singular')
                ? $this->translator->get('panneau::resources.' . $id . '_Singular')
                : Str::title($singularName),
            'a_singular' => $this->translator->has('panneau::resources.' . $id . '_a_singular')
                ? $this->translator->get('panneau::resources.' . $id . '_a_singular')
                : $this->translator->get('panneau::resources.a_singular', [
                    'resource' => $singular,
                ]),
            'A_singular' => $this->translator->has('panneau::resources.' . $id . '_A_singular')
                ? $this->translator->get('panneau::resources.' . $id . '_A_singular')
                : $this->translator->get('panneau::resources.A_singular', [
                    'resource' => $singular,
                ]),
            'the_singular' => $this->translator->has('panneau::resources.' . $id . '_the_singular')
                ? $this->translator->get('panneau::resources.' . $id . '_the_singular')
                : $this->translator->get('panneau::resources.the_singular', [
                    'resource' => $singular,
                ]),
            'The_singular' => $this->translator->has('panneau::resources.' . $id . '_The_singular')
                ? $this->translator->get('panneau::resources.' . $id . '_The_singular')
                : $this->translator->get('panneau::resources.The_singular', [
                    'resource' => $singular,
                ]),
        ];
    }

    public function messages(): ?array
    {
        return $this->resource->messages();
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
