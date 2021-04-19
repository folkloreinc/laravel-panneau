<?php

namespace Panneau;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Collection;
use Illuminate\Support\Arr;
use Panneau\Contracts\Definition as DefinitionContract;
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

    public function locale(): string
    {
        return $this->app['config']->get('panneau.locale', $this->app->getLocale());
    }

    public function messages(): Collection
    {
        $defaultNamespace = 'panneau';
        $locale = $this->locale();
        $namespaces = $this->app['config']->get('panneau.messages', [$defaultNamespace]);
        $messages = [];

        foreach ($namespaces as $namespace) {
            $texts = $this->app['translator']->get($namespace, [], $locale);
            if (is_null($texts)) {
                continue;
            }
            $texts = is_string($texts) ? [$texts] : Arr::dot($texts);
            foreach ($texts as $key => $value) {
                if (sizeof($texts) === 1 && $key === 0) {
                    $key = $namespace;
                } elseif ($namespace !== $defaultNamespace) {
                    $key = $namespace . '.' . $key;
                }
                $messages[$key] = preg_replace('/\:([a-z][a-z0-9\_\-]+)/', '{$1}', $value);
            }
        }

        return collect($messages);
    }

    public function routes(): Collection
    {
        return $this->panneau->router()->getRoutes();
    }

    public function resources(): Collection
    {
        return $this->panneau->resources();
    }

    public function toArray()
    {
        return [
            'routes' => $this->panneau->router()->toRoutesArray(),
            'resources' => $this->resources()->toArray()
        ];
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
