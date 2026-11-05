<?php

namespace Panneau\Dashboard;

use Closure;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use LogicException;
use Panneau\Context\Context;
use Panneau\Contracts\WidgetData;
use Panneau\Exceptions\WidgetDataException;
use Panneau\Lists\Filters\Filter;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceRegistry;

/**
 * The widgets of the dashboard, declared with `Panneau::dashboard()`, and their data
 * (panneau-js decision 0064).
 *
 * @internal
 */
final class Dashboard
{
    /**
     * @var list<Widget>|Closure|null
     */
    private array|Closure|null $widgets = null;

    public function __construct(
        private readonly Container $container,
        private readonly ResourceRegistry $resources,
        private readonly Cache $cache,
    ) {}

    /**
     * @param  list<Widget>|Closure  $widgets
     */
    public function setWidgets(array|Closure $widgets): void
    {
        $this->widgets = $widgets;
    }

    /**
     * The widgets of a user, in order: those whose ability they have.
     *
     * @return list<Widget>
     */
    public function widgets(?Authenticatable $user): array
    {
        $widgets = $this->widgets instanceof Closure
            ? $this->container->call($this->widgets, ['user' => $user])
            : $this->widgets ?? [];
        if (! is_array($widgets)) {
            throw new LogicException(sprintf('The declaration of the dashboard gives %s instead of an array of widgets.', get_debug_type($widgets)));
        }
        $result = [];
        foreach (array_values($widgets) as $widget) {
            if (! $widget instanceof Widget) {
                throw new LogicException(sprintf('The dashboard has a %s: widgets are Panneau\Dashboard\Widget builders.', get_debug_type($widget)));
            }
            if (isset($result[$widget->id()])) {
                throw new LogicException(sprintf('The dashboard has two widgets "%s": ids are unique.', $widget->id()));
            }
            if ($this->allows($user, $widget->ability())) {
                $result[$widget->id()] = $widget;
            }
        }

        return array_values($result);
    }

    /**
     * The widgets of a user in the definition. Checked when it is built: a widget without data,
     * on a resource that isn't registered, with a filter its list doesn't have, or a stat on a
     * list paged with cursors, which has no total, throws a `LogicException`.
     *
     * @return list<array<string, mixed>>
     */
    public function definition(?Authenticatable $user): array
    {
        return array_map(function (Widget $widget): array {
            $name = $widget->getResource();
            if ($name === null) {
                if ($widget->getData() === null) {
                    throw new LogicException(sprintf('The widget "%s" has no data: give it a resource() or using().', $widget->id()));
                }

                return $widget->toDefinition();
            }

            return $widget->toDefinition($this->checkedResource($widget, $name)->id());
        }, $this->widgets($user));
    }

    /**
     * A widget of the user's dashboard, by id.
     */
    public function find(?Authenticatable $user, string $id): ?Widget
    {
        foreach ($this->widgets($user) as $widget) {
            if ($widget->id() === $id) {
                return $widget;
            }
        }

        return null;
    }

    /**
     * The data of a widget with data of its own, for the user, checked against the shape of
     * its type, and kept for `cache()` seconds when it has the right shape.
     *
     * @return array<string, mixed>
     */
    public function data(Widget $widget, ?Authenticatable $user): array
    {
        $seconds = $widget->getCache();
        if ($seconds === null) {
            return $this->compute($widget, $user);
        }
        $key = $this->cacheKey($widget, $user);
        $cached = $this->cache->get($key);
        if (is_array($cached)) {
            /** @var array<string, mixed> $cached */
            return $cached;
        }
        $data = $this->compute($widget, $user);
        $this->cache->put($key, $data, $seconds);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function compute(Widget $widget, ?Authenticatable $user): array
    {
        $source = $widget->getData();
        $data = match (true) {
            $source instanceof Closure => $this->container->call($source, ['user' => $user]),
            is_string($source) => $this->dataOf($source, $user),
            default => throw new LogicException(sprintf('The widget "%s" has no data of its own.', $widget->id())),
        };
        if (! is_array($data)) {
            throw new WidgetDataException($widget->id(), $widget->type(), ['']);
        }
        $wrong = WidgetShape::check($widget->type(), $data);
        if ($wrong !== []) {
            throw new WidgetDataException($widget->id(), $widget->type(), $wrong);
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * @param  class-string<WidgetData>  $class
     * @return array<string, mixed>
     */
    private function dataOf(string $class, ?Authenticatable $user): array
    {
        $instance = $this->container->make($class);

        return $instance->data($user);
    }

    /**
     * The key of the data of a widget for a user, in the current locale and context.
     */
    private function cacheKey(Widget $widget, ?Authenticatable $user): string
    {
        $locale = $this->container->make(Application::class)->getLocale();
        $context = $this->container->bound(Context::class) ? $this->container->make(Context::class)->all() : [];

        return 'panneau:dashboard:'.sha1((string) json_encode([
            $widget->id(),
            $user?->getAuthIdentifier(),
            $locale,
            $context,
        ]));
    }

    private function checkedResource(Widget $widget, string $name): Resource
    {
        $resource = $this->resources->resolve($name);
        if ($resource === null) {
            throw new LogicException(sprintf('The widget "%s" reads the resource %s, which is not registered.', $widget->id(), $name));
        }
        if ($widget->type() === 'stat' && $this->resources->cursorPaginated($resource)) {
            throw new LogicException(sprintf('The stat "%s" counts the list of "%s", which is paged with cursors and has no total.', $widget->id(), $resource->id()));
        }
        $filters = array_map(fn (Filter $filter): string => $filter->id(), $this->resources->list($resource)->getFilters());
        foreach (array_keys($widget->getQuery()) as $key) {
            if (preg_match('/^filter\[([^\]]+)\]/', $key, $matches) === 1 && ! in_array($matches[1], $filters, true)) {
                throw new LogicException(sprintf('The widget "%s" filters the list of "%s" by "%s", which is not one of its filters.', $widget->id(), $resource->id(), $matches[1]));
            }
        }

        return $resource;
    }

    /**
     * Whether the user has the ability of a widget: a Gate ability, or a closure. Without one,
     * every user.
     *
     * @param  string|(Closure(?Authenticatable): bool)|null  $ability
     */
    private function allows(?Authenticatable $user, string|Closure|null $ability): bool
    {
        if ($ability === null) {
            return true;
        }
        if ($ability instanceof Closure) {
            return $this->container->call($ability, ['user' => $user]) === true;
        }

        return $this->container->make(Gate::class)->forUser($user)->allows($ability);
    }
}
