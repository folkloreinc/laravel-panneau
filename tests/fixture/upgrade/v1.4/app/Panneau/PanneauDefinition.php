<?php

namespace App\Panneau;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Collection;
use Panneau\Contracts\Definition;
use Panneau\Contracts\PanneauIntl;

class PanneauDefinition implements Definition, Arrayable, Jsonable
{
    public function __construct(protected Definition $definition)
    {
    }

    public function name(): string
    {
        return 'Example Agency';
    }

    public function routes(): Collection
    {
        return $this->definition->routes();
    }

    public function resources(): Collection
    {
        return $this->definition->resources();
    }

    public function intl(): PanneauIntl
    {
        return $this->definition->intl();
    }

    public function menus(): array
    {
        return [
            ['label' => 'Pages', 'href' => route('panneau.resources.pages.index')],
        ];
    }

    public function toArray()
    {
        return array_merge($this->definition->toArray(), ['menus' => $this->menus()]);
    }

    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }
}
