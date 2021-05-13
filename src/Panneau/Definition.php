<?php

namespace Panneau;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Collection;
use Illuminate\Support\Arr;
use Panneau\Contracts\Definition as DefinitionContract;
use Panneau\Contracts\PanneauIntl as PanneauIntlContract;
use Panneau\Contracts\Panneau as PanneauContract;

class Definition implements DefinitionContract, Arrayable, Jsonable
{
    protected $panneau;
    protected $app;

    public function __construct(PanneauContract $panneau, $app)
    {
        $this->panneau = $panneau;
        $this->app = $app;
    }

    public function name(): string
    {
        return $this->app['config']->get('panneau.name', 'Panneau');
    }

    public function routes(): Collection
    {
        return $this->panneau->router()->getRoutes();
    }

    public function resources(): Collection
    {
        return $this->panneau->resources();
    }

    public function intl(): PanneauIntlContract
    {
        return new PanneauIntl($this, $this->app);
    }

    public function settings(): ?array
    {
        return $this->panneau->settings();
    }

    public function toArray()
    {
        $intl = $this->intl();

        $data = [
            'name' => $this->name(),
            'routes' => $this->panneau->router()->toArray(),
            'resources' => $this->resources()->toArray(),
            'intl' => $intl instanceof Arrayable ? $intl->toArray() : $intl,
        ];

        $settings = $this->settings();
        if (isset($settings)) {
            $data['settings'] = $settings;
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
