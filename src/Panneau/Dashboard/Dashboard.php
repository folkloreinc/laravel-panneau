<?php

namespace Panneau\Dashboard;

use Closure;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use LogicException;
use Panneau\Aggregates\Group;
use Panneau\Aggregates\Measure;
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
                $definitions[$id] = $this->definitionOf($user, $widgets, null, $page->getPeriod());
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

        return $this->definitionOf($user, $this->widgets($user, self::HOME, $pages) ?? [], null, $this->pagePeriod($user, self::HOME, $pages));
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
        $period = $summary->getPeriod();
        $definition = ['widgets' => $this->definitionOf($user, $this->summaryWidgets($user, $resource) ?? [], $resource, $period)];
        if ($summary->collapsesList()) {
            $definition['list'] = 'collapsed';
        }
        if ($period !== null) {
            $filters = $this->dateRangeFilters($resource);
            if ($period->filter !== null && ! in_array($period->filter, $filters, true)) {
                throw new LogicException(sprintf('The period of the summary of "%s" sets the filter "%s", which isn\'t a date-range filter of its list.', $resource->id(), $period->filter));
            }
            if ($filters === []) {
                throw new LogicException(sprintf('The period of the summary of "%s" is a date-range filter of its list, which has none.', $resource->id()));
            }
            $definition['period'] = $period->toDefinition();
        }

        return $definition;
    }

    /**
     * The period of the summary of a resource: null without one.
     */
    public function summaryPeriod(Resource $resource): ?Period
    {
        return $this->summaryOf($resource)?->getPeriod();
    }

    /**
     * The period of the dashboard of a page: that of its `Page::dashboard()`, null without one.
     *
     * @param  array<string, Page>|null  $pages  The dashboard pages of the user
     *                                           (`Navigation::dashboardPages()`), when they are
     *                                           already known.
     */
    public function pagePeriod(?Authenticatable $user, string $page = self::HOME, ?array $pages = null): ?Period
    {
        $pages ??= $this->navigation->dashboardPages($user);

        return ($pages[$page] ?? null)?->getPeriod();
    }

    /**
     * The ids of the `date-range` filters of the list of a resource.
     *
     * @return list<string>
     */
    private function dateRangeFilters(Resource $resource): array
    {
        return array_values(array_map(
            fn (Filter $filter): string => $filter->id(),
            array_filter($this->resources->list($resource)->getFilters(), fn (Filter $filter): bool => $filter->type() === 'date-range'),
        ));
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
     * @param  \Panneau\Resources\Resource|null  $summary  The resource of a summary, or null for a
     *                                                     dashboard.
     * @param  Period|null  $period  The period of the dashboard or the summary.
     * @return list<array<string, mixed>>
     */
    private function definitionOf(?Authenticatable $user, array $widgets, ?Resource $summary = null, ?Period $period = null): array
    {
        $definitions = [];
        foreach ($widgets as $widget) {
            $name = $widget->getResource();
            if ($widget->getFollowsList() !== null && $summary === null) {
                throw new LogicException(sprintf('The widget "%s" follows a list (followsList()): only the widgets of the summary of a resource do.', $widget->id()));
            }
            if ($widget->getPeriod() !== null && $period === null) {
                throw new LogicException(sprintf('The widget "%s" follows a period (period()), but its %s has none.', $widget->id(), $summary !== null ? 'summary' : 'dashboard'));
            }
            if ($name === null && $widget->getSources() !== []) {
                $sources = $this->checkedSources($user, $widget, $period);
                if ($sources !== null) {
                    $definitions[] = $widget->toDefinition(null, $sources);
                }

                continue;
            }
            if ($name === null) {
                if (is_string($widget->getPeriod())) {
                    throw new LogicException(sprintf('The widget "%s" follows the period with the filter "%s" of no list: without a resource(), period() can only be false.', $widget->id(), $widget->getPeriod()));
                }
                if ($widget->getData() === null) {
                    throw new LogicException(sprintf('The widget "%s" has no data: give it a resource() or using().', $widget->id()));
                }
                if ($widget->measuresResource()) {
                    throw new LogicException(sprintf('The widget "%s" measures a resource (count(), sum(), per(), by(), points(), split() or target()) without one: give it a resource().', $widget->id()));
                }
                $definitions[] = $widget->toDefinition();

                continue;
            }
            $resource = $this->checkedResource($widget, $name);
            $filter = $widget->getPeriod();
            if (is_string($filter) && ! in_array($filter, $this->dateRangeFilters($resource), true)) {
                throw new LogicException(sprintf('The widget "%s" follows the period with the filter "%s", which isn\'t a date-range filter of the list of "%s".', $widget->id(), $filter, $resource->id()));
            }
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
        $groups = [$widget->getGroup(), $widget->getPoints()];
        if ($widget->getBy() !== null) {
            $groups[] = Group::field($widget->getBy());
        }
        if ($widget->getSplit() !== null) {
            $groups[] = Group::field($widget->getSplit());
        }

        return $this->hides($resource, $user, $widget->getMeasure(), $groups);
    }

    /**
     * Whether the user can't see a field that a widget, or one of its sources, reads of the
     * aggregates of a resource: its sum, its groups. Also when the aggregates have no field the
     * user can see.
     *
     * @param  list<Group|null>  $groups
     */
    private function hides(Resource $resource, ?Authenticatable $user, ?Measure $measure, array $groups): bool
    {
        $aggregates = $this->aggregates->forUser($resource, $user);
        if ($aggregates === null) {
            return true;
        }
        if ($measure?->field !== null && ! in_array($measure->field, $aggregates['sum'], true)) {
            return true;
        }
        foreach ($groups as $group) {
            if ($group !== null && ! in_array($group->field, $group->isPeriod() ? $aggregates['dates'] : $aggregates['groupBy'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks the sources of a `series` or the rows of a `table` (panneau-js decision 0089), as
     * `lintDefinition()` does: each on a registered resource whose list declares aggregates, with
     * a sum of their `sum`, a period of their `dates` (required for a line of a series), a
     * `date-range` filter for its period, and filters of the list in its query and its alert. The
     * lines of a series, and the trends of a table, share one period. A `LogicException`
     * otherwise.
     *
     * @param  Period|null  $period  The period of the dashboard or the summary.
     * @return list<string>|null The ids of their resources, in order, or null when the user can't
     *                           see a field that one of them reads.
     */
    private function checkedSources(?Authenticatable $user, Widget $widget, ?Period $period): ?array
    {
        $what = $widget->type() === 'table' ? 'row' : 'source';
        if ($widget->getMeasure() !== null || $widget->getTarget() !== null || $widget->getSplit() !== null) {
            throw new LogicException(sprintf('The %s "%s" reads the resources of its %ss: count(), sum() and split() go on each of them.', $widget->type(), $widget->id(), $what));
        }
        $ids = [];
        $hidden = false;
        $intervals = [];
        foreach ($widget->getSources() as $source) {
            $name = sprintf('The %s "%s" of the %s "%s"', $what, $source->getLabel(), $widget->type(), $widget->id());
            $resource = $this->resources->resolve($source->getResource());
            if ($resource === null) {
                throw new LogicException(sprintf('%s reads the resource %s, which is not registered.', $name, $source->getResource()));
            }
            $aggregates = $this->aggregates->declared($resource);
            if ($aggregates === null) {
                throw new LogicException(sprintf('%s reads the aggregates of "%s", whose list declares none: add aggregates() to its list.', $name, $resource->id()));
            }
            $sum = $source->getMeasure()?->field;
            if ($sum !== null && ! in_array($sum, $aggregates['sum'], true)) {
                throw new LogicException(sprintf('%s sums "%s" of "%s", which isn\'t in the sum of the aggregates of its list.', $name, $sum, $resource->id()));
            }
            $group = $source->getGroup() ?? ($widget->type() === 'table' ? $widget->getPoints() : $widget->getGroup());
            if ($group === null && $widget->type() === 'series') {
                throw new LogicException(sprintf('%s needs per(): the days, weeks or months of a date, on the source or on the series.', $name));
            }
            if ($group !== null) {
                if (! in_array($group->field, $aggregates['dates'], true)) {
                    throw new LogicException(sprintf('%s groups "%s" by "%s", which isn\'t in the dates of the aggregates of its list.', $name, $resource->id(), $group->field));
                }
                $intervals[] = $group->interval?->value;
            }
            if (is_string($source->getPeriod()) && $period === null) {
                throw new LogicException(sprintf('%s follows a period (period()), but its overview has none.', $name));
            }
            $filter = $source->getPeriod() ?? $widget->getPeriod();
            if (is_string($filter) && ! in_array($filter, $this->dateRangeFilters($resource), true)) {
                throw new LogicException(sprintf('%s follows the period with the filter "%s", which isn\'t a date-range filter of the list of "%s".', $name, $filter, $resource->id()));
            }
            $filters = array_map(fn (Filter $filter): string => $filter->id(), $this->resources->list($resource)->getFilters());
            foreach ([...array_keys($source->getQuery()), ...array_keys($source->getAlertQuery())] as $key) {
                if (preg_match('/^filter\[([^\]]+)\]/', $key, $matches) === 1 && ! in_array($matches[1], $filters, true)) {
                    throw new LogicException(sprintf('%s filters the list of "%s" by "%s", which is not one of its filters.', $name, $resource->id(), $matches[1]));
                }
            }
            $hidden = $hidden || $this->hides($resource, $user, $source->getMeasure(), [$group]);
            $ids[] = $resource->id();
        }
        $intervals = array_values(array_unique($intervals));
        if (count($intervals) > 1) {
            throw new LogicException(sprintf('The %s of the %s "%s" have different periods (%s): they share one.', $widget->type() === 'table' ? 'trends' : 'lines', $widget->type(), $widget->id(), implode(', ', $intervals)));
        }

        return $hidden ? null : $ids;
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
        return WidgetShape::normalize($widget->type(), $data);
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
            $request->period?->option,
            $request->period?->from,
            $request->period?->to,
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
     * Checks what a widget reads of the aggregates of a resource (panneau-js decisions 0084 and
     * 0089): a `series` by the period of one of their `dates`, split by one of their `groupBy`
     * that the list filters by, a `breakdown` and the parts of a `metric` by one of their
     * `groupBy`, the points of a `metric` by the period of one of their `dates`, and a sum of one
     * of their `sum`.
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
        $split = $widget->getSplit();
        if ($split !== null) {
            if (! in_array($split, $aggregates['groupBy'], true)) {
                throw new LogicException(sprintf('The series "%s" splits "%s" by "%s", which isn\'t in the groupBy of the aggregates of its list.', $widget->id(), $resource->id(), $split));
            }
            $filters = array_filter($this->resources->list($resource)->getFilters(), fn (Filter $filter): bool => $filter->id() === $split && $filter->type() !== 'date-range');
            if ($filters === []) {
                throw new LogicException(sprintf('The series "%s" splits "%s" by "%s", which isn\'t a filter of its list: each of its lines reads the list filtered by its value.', $widget->id(), $resource->id(), $split));
            }
        }
        $points = $widget->getPoints();
        if ($points !== null && ! in_array($points->field, $aggregates['dates'], true)) {
            throw new LogicException(sprintf('The %s "%s" has points by "%s" of "%s", which isn\'t in the dates of the aggregates of its list.', $widget->type(), $widget->id(), $points->field, $resource->id()));
        }
        $by = $widget->getBy();
        if ($by !== null && ! in_array($by, $aggregates['groupBy'], true)) {
            throw new LogicException(sprintf('The %s "%s" has parts by "%s" of "%s", which isn\'t in the groupBy of the aggregates of its list.', $widget->type(), $widget->id(), $by, $resource->id()));
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
