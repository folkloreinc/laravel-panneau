<?php

namespace Panneau\Dashboard;

use Closure;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use LogicException;
use Panneau\Aggregates\ResourceAggregates;
use Panneau\Context\Context;
use Panneau\Contracts\WidgetData;
use Panneau\Exceptions\WidgetDataException;
use Panneau\Lists\Filters\Filter;
use Panneau\Navigation\Navigation;
use Panneau\Navigation\Page;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceRegistry;

/**
 * The widgets of the dashboards and the summaries, and their data (panneau-js decisions 0064 and
 * 0084): those of the home page, declared with `Panneau::dashboard()` or
 * `Page::dashboard('home')`, those of each dashboard page (`Page::dashboard()`), and those of the
 * summary above the list of a resource (`Resource::summary()`).
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
        private readonly ResourceAggregates $aggregates,
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
                $definitions[$id] = $this->definitionOf($user, $widgets);
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

        return $this->definitionOf($user, $this->widgets($user, self::HOME, $pages) ?? []);
    }

    /**
     * The widgets of the summary of a resource for a user, in order: those whose ability they
     * have. Null for a resource without a summary.
     *
     * @return list<Widget>|null
     */
    public function summaryWidgets(?Authenticatable $user, Resource $resource): ?array
    {
        $summary = $this->summaryOf($resource);

        return $summary !== null
            ? $this->filter($user, $summary->getWidgets(), sprintf('The summary of "%s"', $resource->id()))
            : null;
    }

    /**
     * The summary of a resource in the definition, for a user, checked as the widgets of the
     * dashboards: its widgets, and `list: "collapsed"`. Null without one.
     *
     * @return array<string, mixed>|null
     */
    public function summaryDefinition(?Authenticatable $user, Resource $resource): ?array
    {
        $summary = $this->summaryOf($resource);
        if ($summary === null) {
            return null;
        }
        $definition = ['widgets' => $this->definitionOf($user, $this->summaryWidgets($user, $resource) ?? [], $resource)];
        if ($summary->collapsesList()) {
            $definition['list'] = 'collapsed';
        }

        return $definition;
    }

    /**
     * A widget of the summary of a resource for the user, by id.
     */
    public function findInSummary(?Authenticatable $user, Resource $resource, string $id): ?Widget
    {
        foreach ($this->summaryWidgets($user, $resource) ?? [] as $widget) {
            if ($widget->id() === $id) {
                return $widget;
            }
        }

        return null;
    }

    /**
     * The summary of a resource: what `summary()` returns, as a `Summary`. Null for none.
     */
    private function summaryOf(Resource $resource): ?Summary
    {
        $summary = $resource->summary();
        if (is_array($summary)) {
            return $summary !== [] ? Summary::make($summary) : null;
        }

        return $summary;
    }

    /**
     * Widgets in the definition. Checked when it is built: a widget without data, on a resource
     * that isn't registered, with a filter its list doesn't have, a stat that counts a list paged
     * with cursors, which has no total, or that measures or groups by what the aggregates of the
     * list don't declare, throws a `LogicException`, and so does `followsList()` out of a summary,
     * or on another resource. A widget on aggregates of fields the user can't see is left out for
     * them.
     *
     * @param  list<Widget>  $widgets
     * @param  resource|null  $summary  The resource of a summary, or null for a dashboard.
     * @return list<array<string, mixed>>
     */
    private function definitionOf(?Authenticatable $user, array $widgets, ?Resource $summary = null): array
    {
        $definitions = [];
        foreach ($widgets as $widget) {
            $name = $widget->getResource();
            if ($widget->getFollowsList() !== null && $summary === null) {
                throw new LogicException(sprintf('The widget "%s" follows a list (followsList()): only the widgets of the summary of a resource do.', $widget->id()));
            }
            if ($name === null) {
                if ($widget->getData() === null) {
                    throw new LogicException(sprintf('The widget "%s" has no data: give it a resource() or using().', $widget->id()));
                }
                if ($widget->getMeasure() !== null || $widget->getGroup() !== null || $widget->getTarget() !== null) {
                    throw new LogicException(sprintf('The widget "%s" measures a resource (count(), sum(), per(), by() or target()) without one: give it a resource().', $widget->id()));
                }
                $definitions[] = $widget->toDefinition();

                continue;
            }
            $resource = $this->checkedResource($widget, $name);
            if ($widget->getFollowsList() !== null && $summary !== null && $resource->id() !== $summary->id()) {
                throw new LogicException(sprintf('The widget "%s" reads "%s": it doesn\'t follow the list of "%s" (followsList()).', $widget->id(), $resource->id(), $summary->id()));
            }
            if ($this->aggregatesHidden($widget, $resource, $user)) {
                continue;
            }
            $definitions[] = $widget->toDefinition($resource->id());
        }

        return $definitions;
    }

    /**
     * Whether a widget on the aggregates of a resource reads a field the user can't see.
     */
    private function aggregatesHidden(Widget $widget, Resource $resource, ?Authenticatable $user): bool
    {
        if (! $widget->readsAggregates()) {
            return false;
        }
        $aggregates = $this->aggregates->forUser($resource, $user);
        $sum = $widget->getMeasure()?->field;
        $group = $widget->getGroup();

        return $aggregates === null
            || ($sum !== null && ! in_array($sum, $aggregates['sum'], true))
            || ($group !== null && ! in_array($group->field, $group->isPeriod() ? $aggregates['dates'] : $aggregates['groupBy'], true));
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
            $request->resource,
            $request->query?->filters,
            $request->query?->search,
        ]));
    }

    private function checkedResource(Widget $widget, string $name): Resource
    {
        $resource = $this->resources->resolve($name);
        if ($resource === null) {
            throw new LogicException(sprintf('The widget "%s" reads the resource %s, which is not registered.', $widget->id(), $name));
        }
        if ($widget->readsAggregates()) {
            $this->checkAggregates($widget, $resource);
        } elseif ($widget->type() === 'stat' && $this->resources->cursorPaginated($resource)) {
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
     * Checks what a widget reads of the aggregates of a resource (panneau-js decision 0084): a
     * `series` by the period of one of their `dates`, a `breakdown` by one of their `groupBy`,
     * and a sum of one of their `sum`.
     */
    private function checkAggregates(Widget $widget, Resource $resource): void
    {
        $aggregates = $this->aggregates->declared($resource);
        if ($aggregates === null) {
            throw new LogicException(sprintf('The %s "%s" reads the aggregates of "%s", whose list declares none: add aggregates() to its list.', $widget->type(), $widget->id(), $resource->id()));
        }
        $sum = $widget->getMeasure()?->field;
        if ($sum !== null && ! in_array($sum, $aggregates['sum'], true)) {
            throw new LogicException(sprintf('The %s "%s" sums "%s" of "%s", which isn\'t in the sum of the aggregates of its list.', $widget->type(), $widget->id(), $sum, $resource->id()));
        }
        $group = $widget->getGroup();
        if ($widget->type() === 'series') {
            if ($group === null || ! $group->isPeriod()) {
                throw new LogicException(sprintf('The series "%s" on "%s" needs per(): the days, weeks or months of a date.', $widget->id(), $resource->id()));
            }
            if (! in_array($group->field, $aggregates['dates'], true)) {
                throw new LogicException(sprintf('The series "%s" groups "%s" by "%s", which isn\'t in the dates of the aggregates of its list.', $widget->id(), $resource->id(), $group->field));
            }
        }
        if ($widget->type() === 'breakdown') {
            if ($group === null) {
                throw new LogicException(sprintf('The breakdown "%s" on "%s" needs by(): the field whose values are its parts.', $widget->id(), $resource->id()));
            }
            if (! in_array($group->field, $aggregates['groupBy'], true)) {
                throw new LogicException(sprintf('The breakdown "%s" groups "%s" by "%s", which isn\'t in the groupBy of the aggregates of its list.', $widget->id(), $resource->id(), $group->field));
            }
        }
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
