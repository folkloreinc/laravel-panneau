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
use Panneau\Navigation\Navigation;
use Panneau\Navigation\Page;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceRegistry;

/**
 * The widgets of the dashboards and their data (panneau-js decisions 0064 and 0084): those of the
 * home page, declared with `Panneau::dashboard()` or `Page::dashboard('home')`, and those of each
 * dashboard page (`Page::dashboard()`).
 *
 * @internal
 */
final class Dashboard
{
    /**
     * The page whose dashboard is the home page, and the page of a widget asked for without one.
     */
    public const HOME = 'home';

    /**
     * @var list<Widget>|Closure|null
     */
    private array|Closure|null $widgets = null;

    public function __construct(
        private readonly Container $container,
        private readonly ResourceRegistry $resources,
        private readonly Cache $cache,
        private readonly Navigation $navigation,
    ) {}

    /**
     * @param  list<Widget>|Closure  $widgets
     */
    public function setWidgets(array|Closure $widgets): void
    {
        $this->widgets = $widgets;
    }

    /**
     * The widgets of the dashboard of a page for a user, in order: those whose ability they have
     * (`Widget::can()`). Null for a page that has no dashboard for the user: an unknown page, a
     * page that isn't a dashboard, or one the user can't view (`Page::can()`). The home page has
     * the widgets of `Page::dashboard('home')`, or of `Panneau::dashboard()`.
     *
     * @param  array<string, Page>|null  $pages  The dashboard pages of the user
     *                                           (`Navigation::dashboardPages()`), when they are
     *                                           already known.
     * @return list<Widget>|null
     */
    public function widgets(?Authenticatable $user, string $page = self::HOME, ?array $pages = null): ?array
    {
        $pages ??= $this->navigation->dashboardPages($user);
        $dashboard = $pages[$page] ?? null;
        $declared = $dashboard?->getWidgets();
        if ($page === self::HOME && $declared !== null && $this->widgets !== null) {
            throw new LogicException('The home page has widgets of Page::dashboard(\'home\') and of Panneau::dashboard(): set only one.');
        }
        if ($dashboard !== null && ! $this->navigation->canView($user, $dashboard)) {
            return null;
        }
        if ($declared === null) {
            if ($page !== self::HOME) {
                return null;
            }
            $declared = $this->widgets ?? [];
        }

        return $this->filter($user, $declared, $this->placeName($page));
    }

    /**
     * The dashboard pages whose widgets go in the definition for a user, with their widgets,
     * checked: the pages of `Page::dashboard()->widgets()` that the user can view. The home page
     * is among them when its widgets are those of `Page::dashboard('home')`; those of
     * `Panneau::dashboard()` are `homeDefinition()`.
     *
     * @param  array<string, Page>  $pages  The dashboard pages of the user
     *                                      (`Navigation::dashboardPages()`).
     * @return array<string, list<array<string, mixed>>>
     */
    public function pagesDefinition(?Authenticatable $user, array $pages): array
    {
        $definitions = [];
        foreach ($pages as $id => $page) {
            if ($page->getWidgets() === null) {
                continue;
            }
            $widgets = $this->widgets($user, $id, $pages);
            if ($widgets !== null) {
                $definitions[$id] = $this->definitionOf($widgets);
            }
        }

        return $definitions;
    }

    /**
     * The widgets of `Panneau::dashboard()` for a user in the definition, the `dashboard.widgets`
     * of the home page, checked. Empty when the home page has the widgets of
     * `Page::dashboard('home')`.
     *
     * @param  array<string, Page>  $pages  The dashboard pages of the user
     *                                      (`Navigation::dashboardPages()`).
     * @return list<array<string, mixed>>
     */
    public function homeDefinition(?Authenticatable $user, array $pages): array
    {
        if (($pages[self::HOME] ?? null)?->getWidgets() !== null) {
            // Throws when both are set
            $this->widgets($user, self::HOME, $pages);

            return [];
        }

        return $this->definitionOf($this->widgets($user, self::HOME, $pages) ?? []);
    }

    /**
     * Widgets in the definition. Checked when it is built: a widget without data, on a resource
     * that isn't registered, with a filter its list doesn't have, or a stat on a list paged with
     * cursors, which has no total, throws a `LogicException`.
     *
     * @param  list<Widget>  $widgets
     * @return list<array<string, mixed>>
     */
    private function definitionOf(array $widgets): array
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
        }, $widgets);
    }

    /**
     * A widget of the dashboard of a page of the user, by id.
     */
    public function find(?Authenticatable $user, string $id, string $page = self::HOME): ?Widget
    {
        foreach ($this->widgets($user, $page) ?? [] as $widget) {
            if ($widget->id() === $id) {
                return $widget;
            }
        }

        return null;
    }

    /**
     * The widgets of a declaration for a user, in order: those whose ability they have.
     *
     * @param  list<Widget>|Closure  $declaration
     * @return list<Widget>
     */
    private function filter(?Authenticatable $user, array|Closure $declaration, string $place): array
    {
        $widgets = $declaration instanceof Closure
            ? $this->container->call($declaration, ['user' => $user])
            : $declaration;
        if (! is_array($widgets)) {
            throw new LogicException(sprintf('The declaration of %s gives %s instead of an array of widgets.', lcfirst($place), get_debug_type($widgets)));
        }
        $result = [];
        foreach (array_values($widgets) as $widget) {
            if (! $widget instanceof Widget) {
                throw new LogicException(sprintf('%s has a %s: widgets are Panneau\Dashboard\Widget builders.', $place, get_debug_type($widget)));
            }
            if (isset($result[$widget->id()])) {
                throw new LogicException(sprintf('%s has two widgets "%s": ids are unique.', $place, $widget->id()));
            }
            if ($this->allows($user, $widget->ability())) {
                $result[$widget->id()] = $widget;
            }
        }

        return array_values($result);
    }

    /**
     * The name of a dashboard in messages: `The dashboard` of the home page, `The dashboard page
     * "activity"` of another page.
     */
    private function placeName(string $page): string
    {
        return $page === self::HOME ? 'The dashboard' : sprintf('The dashboard page "%s"', $page);
    }

    /**
     * The data of a widget with data of its own, for the user and the request, checked against
     * the shape of its type, and kept for `cache()` seconds when it has the right shape. The data
     * gets the request from the container (`WidgetRequest`).
     *
     * @return array<string, mixed>
     */
    public function data(Widget $widget, ?Authenticatable $user, ?WidgetRequest $request = null): array
    {
        $request ??= new WidgetRequest($widget->id());
        $seconds = $widget->getCache();
        if ($seconds === null) {
            return $this->compute($widget, $user, $request);
        }
        $key = $this->cacheKey($widget, $user, $request);
        $cached = $this->cache->get($key);
        if (is_array($cached)) {
            /** @var array<string, mixed> $cached */
            return $cached;
        }
        $data = $this->compute($widget, $user, $request);
        $this->cache->put($key, $data, $seconds);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function compute(Widget $widget, ?Authenticatable $user, WidgetRequest $request): array
    {
        $source = $widget->getData();
        $bound = $this->container->bound(WidgetRequest::class);
        $previous = $bound ? $this->container->make(WidgetRequest::class) : null;
        $this->container->instance(WidgetRequest::class, $request);
        try {
            $data = match (true) {
                $source instanceof Closure => $this->container->call($source, ['user' => $user, WidgetRequest::class => $request]),
                is_string($source) => $this->dataOf($source, $user),
                default => throw new LogicException(sprintf('The widget "%s" has no data of its own.', $widget->id())),
            };
        } finally {
            if ($previous instanceof WidgetRequest) {
                $this->container->instance(WidgetRequest::class, $previous);
            } else {
                $this->container->forgetInstance(WidgetRequest::class);
            }
        }
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
     * The key of the data of a widget for a user and a request, in the current locale and
     * context: the same id on two pages is two widgets.
     */
    private function cacheKey(Widget $widget, ?Authenticatable $user, WidgetRequest $request): string
    {
        $locale = $this->container->make(Application::class)->getLocale();
        $context = $this->container->bound(Context::class) ? $this->container->make(Context::class)->all() : [];

        return 'panneau:dashboard:'.sha1((string) json_encode([
            $widget->id(),
            $user?->getAuthIdentifier(),
            $locale,
            $context,
            $request->page,
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
