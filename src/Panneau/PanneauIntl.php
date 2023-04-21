<?php

namespace Panneau;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Collection;
use Illuminate\Support\Arr;
use Panneau\Contracts\PanneauIntl as PanneauIntlContract;
use Panneau\Contracts\Definition as DefinitionContract;

class PanneauIntl implements PanneauIntlContract, Arrayable, Jsonable
{
    protected $definition;
    protected $app;

    public function __construct(DefinitionContract $definition, $app)
    {
        $this->definition = $definition;
        $this->app = $app;
    }

    public function locale(): string
    {
        return $this->app['config']->get('panneau.intl.locale', $this->app->getLocale());
    }

    public function locales(): ?array
    {
        return $this->app['config']->get('panneau.intl.locales', null);
    }

    public function values(): array
    {
        return [
            'name' => $this->definition->name(),
        ];
    }

    public function messages(): array
    {
        $defaultNamespace = $this->app['config']->get(
            'panneau.intl.translations_namespace',
            'panneau'
        );
        $locale = $this->locale();
        $translations = $this->app['config']->get('panneau.intl.translations', [$defaultNamespace]);
        $messages = [];

        foreach ($translations as $translation) {
            $texts = $this->app['translator']->get($translation, [], $locale);
            if (is_null($texts)) {
                continue;
            }
            $texts = is_string($texts) ? [$texts] : Arr::dot($texts);
            foreach ($texts as $key => $value) {
                if (sizeof($texts) === 1 && $key === 0) {
                    $key = $translation;
                } elseif ($translation !== $defaultNamespace && $translation !== '*') {
                    $key = preg_replace('/^panneau\:\:/', '', $translation) . '.' . $key;
                }
                $messages[$key] = preg_replace('/\:([a-z][a-z0-9\_\-]+)/', '{$1}', $value);
            }
        }

        return $messages;
    }

    public function toArray()
    {
        return [
            'locale' => $this->locale(),
            'locales' => $this->locales(),
            'messages' => $this->messages(),
            'values' => $this->values(),
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
