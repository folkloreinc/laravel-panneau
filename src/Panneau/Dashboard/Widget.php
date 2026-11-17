<?php

namespace Panneau\Dashboard;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;
use Panneau\Aggregates\Group;
use Panneau\Aggregates\Interval;
use Panneau\Aggregates\Measure;
use Panneau\Contracts\WidgetData;

/**
 * A widget of a dashboard (panneau-js decisions 0064 and 0084): a `stat`, a `series`, a `list` or
 * a `breakdown`, or a type that a JS plugin draws. The built-in types can read a resource
 * (`resource()`): a `stat` counts its list, a `list` shows its first items, and a `series`, a
 * `breakdown` and a `stat` that sums read its aggregates (`per()`, `by()`, `sum()`). The others
 * get their data from a class or a closure (`using()`), which the app asks for at
 * `GET {path}/api/dashboard/widgets/{id}`.
 */
final class Widget
{
    /**
     * The types that the app draws.
     */
    public const BUILT_IN_TYPES = ['stat', 'series', 'list', 'breakdown'];

    /**
     * The built-in types that can read a resource: `stat` and `list` its list, `series` and
     * `breakdown` its aggregates, and a `stat` that sums too.
     */
    public const RESOURCE_TYPES = ['stat', 'series', 'list', 'breakdown'];

    /**
     * The built-in types that read the aggregates of a resource grouped by `per()` or `by()`.
     */
    public const GROUPED_TYPES = ['series', 'breakdown'];

    /**
     * The periods of the points of a `series`.
     */
    public const SERIES_PERIODS = ['day', 'week', 'month'];

    /**
     * The most items a `list` shows.
     */
    public const LIST_MAX_ITEMS = 5;

    /**
     * The most parts a `breakdown` shows: the rest is its `other`.
     */
    public const BREAKDOWN_MAX_ITEMS = 10;

    /**
     * Query parameters that the app sets on the list of a widget on a resource.
     */
    public const RESERVED_QUERY_PARAMS = ['page', 'perPage', 'cursor'];

    private ?string $label = null;

    private ?int $width = null;

    /**
     * @var array<string, mixed>
     */
    private array $settings = [];

    private ?string $resource = null;

    /**
     * @var array<string, scalar|list<scalar>>
     */
    private array $query = [];

    private ?Measure $measure = null;

    private ?Group $group = null;

    private int|float|null $target = null;

    private ?bool $followsList = null;

    /**
     * @var class-string<WidgetData>|Closure|null
     */
    private string|Closure|null $using = null;

    private ?int $cache = null;

    /**
     * @var string|(Closure(?Authenticatable): bool)|null
     */
    private string|Closure|null $ability = null;

    private function __construct(private readonly string $id, private readonly string $type)
    {
        if ($id === '') {
            throw new InvalidArgumentException('A widget needs an id.');
        }
        if ($type === '') {
            throw new InvalidArgumentException(sprintf('The widget "%s" needs a type.', $id));
        }
    }

    /**
     * A number in large type, with a line of help: a count of the list of a resource, or data
     * of its own.
     */
    public static function stat(string $id): self
    {
        return new self($id, 'stat');
    }

    /**
     * A line of values per day, week or month, with its total: the aggregates of a resource
     * (`resource()->per('day', 'created_at')`), or data of its own.
     */
    public static function series(string $id): self
    {
        return new self($id, 'series');
    }

    /**
     * The parts of a total, as horizontal bars, the largest first: the aggregates of a resource
     * by the values of a field (`resource()->by('status')`), or data of its own.
     */
    public static function breakdown(string $id): self
    {
        return new self($id, 'breakdown');
    }

    /**
     * A few items: the first of the list of a resource, or data of its own.
     */
    public static function list(string $id): self
    {
        return new self($id, 'list');
    }

    /**
     * A widget of a type that a JS plugin draws, with data of its own.
     */
    public static function make(string $id, string $type): self
    {
        return new self($id, $type);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function type(): string
    {
        return $this->type;
    }

    /**
     * The label of the widget: the title of its card, or what a `stat` counts.
     */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * The columns it takes in a grid of 12. Defaults to 3 for a `stat`, 6 for the others.
     */
    public function width(int $width): self
    {
        if ($width < 1 || $width > 12) {
            throw new InvalidArgumentException(sprintf('The widget "%s" has the width %d: widths go from 1 to 12 columns.', $this->id, $width));
        }
        $this->width = $width;

        return $this;
    }

    /**
     * The settings of a widget of a plugin, which its component reads.
     *
     * @param  array<string, mixed>  $settings
     */
    public function settings(array $settings): self
    {
        if (in_array($this->type, self::BUILT_IN_TYPES, true)) {
            throw new InvalidArgumentException(sprintf('The %s "%s" has no settings: give it a resource() or data with using().', $this->type, $this->id));
        }
        $this->settings = $settings;

        return $this;
    }

    /**
     * Reads the list of a resource, a class or an id, with a query written as its query
     * parameters: `['filter[status]' => 'to-judge', 'sort' => '-created_at']`. A `stat` shows
     * its total, a `list` its first items; both link to the list with that query. The user reads
     * the list as on its page, as soon as it changes: there is no endpoint and no cache.
     *
     * @param  array<string, mixed>  $query  Scalars, or lists of scalars.
     */
    public function resource(string $resource, array $query = []): self
    {
        if (! in_array($this->type, self::RESOURCE_TYPES, true)) {
            throw new InvalidArgumentException(sprintf('The %s "%s" can\'t read a resource: only a stat, a series, a list and a breakdown can. Give it data with using().', $this->type, $this->id));
        }
        if ($this->using !== null) {
            throw new InvalidArgumentException(sprintf('The widget "%s" has data of its own (using()): it can\'t also read a resource.', $this->id));
        }
        $checked = [];
        foreach ($query as $key => $value) {
            if (in_array($key, self::RESERVED_QUERY_PARAMS, true)) {
                throw new InvalidArgumentException(sprintf('The query of the widget "%s" sets "%s", which the app sets.', $this->id, $key));
            }
            if (is_scalar($value)) {
                $checked[$key] = $value;

                continue;
            }
            if (! is_array($value) || ! array_is_list($value) || count(array_filter($value, is_scalar(...))) !== count($value)) {
                throw new InvalidArgumentException(sprintf('The query of the widget "%s" has a value of "%s" that isn\'t a scalar or a list of them.', $this->id, $key));
            }
            /** @var list<scalar> $value */
            $checked[$key] = $value;
        }
        $this->resource = $resource;
        $this->query = $checked;

        return $this;
    }

    /**
     * Counts the items of the resource: a `stat` reads the total of its list, a `series` and a
     * `breakdown` count the items of each period or part. The default.
     */
    public function count(): self
    {
        $this->checkAggregates('count()');
        $this->measure = Measure::count();

        return $this;
    }

    /**
     * Sums a field of the items of the resource, among the `sum` of the aggregates of its list:
     * the sum of all of them for a `stat`, of each period or part for a `series` and a
     * `breakdown`. A sum of `money` is in minor units, shown in its currency.
     */
    public function sum(string $field): self
    {
        $this->checkAggregates('sum()');
        $this->measure = Measure::sum($field);

        return $this;
    }

    /**
     * The periods of the points of a `series` on a resource: the days, weeks (from Monday) or
     * months of a date field, among the `dates` of the aggregates of its list.
     */
    public function per(Interval|string $period, string $field): self
    {
        if ($this->type !== 'series') {
            throw new InvalidArgumentException(sprintf('The %s "%s" has no periods: only a series does.', $this->type, $this->id));
        }
        if (! $period instanceof Interval && Interval::tryFrom($period) === null) {
            throw new InvalidArgumentException(sprintf('The series "%s" is per %s: periods are day, week or month.', $this->id, json_encode($period)));
        }
        $this->group = Group::period($period, $field);

        return $this;
    }

    /**
     * The field whose values are the parts of a `breakdown` on a resource, among the `groupBy`
     * of the aggregates of its list. A `select` gives the labels and the tones of its options, a
     * `relation` the titles of its items, and each bar links to the list filtered by its value.
     */
    public function by(string $field): self
    {
        if ($this->type !== 'breakdown') {
            throw new InvalidArgumentException(sprintf('The %s "%s" has no parts: only a breakdown does.', $this->type, $this->id));
        }
        $this->group = Group::field($field);

        return $this;
    }

    /**
     * A target for the number of a `stat` on a resource (a budget, a quota), drawn as a meter
     * under it, in the unit of its value (minor units for a sum of `money`). Past it, the meter
     * is `danger`.
     */
    public function target(int|float $target): self
    {
        if ($this->type !== 'stat') {
            throw new InvalidArgumentException(sprintf('The %s "%s" has no target: only a stat does.', $this->type, $this->id));
        }
        if ($target <= 0) {
            throw new InvalidArgumentException(sprintf('The stat "%s" has the target %s: targets are above 0.', $this->id, json_encode($target)));
        }
        $this->target = $target;

        return $this;
    }

    /**
     * In the summary of a resource (`Resource::summary()`): `false` keeps the widget on the whole
     * resource, without the list's current filters and search. The widgets on the resource of the
     * summary, and those with data of their own, follow the list by default; a widget on another
     * resource doesn't.
     */
    public function followsList(bool $follows = true): self
    {
        $this->followsList = $follows;

        return $this;
    }

    /**
     * The data of the widget: a class that implements `WidgetData`, built from the container,
     * or a closure called through the container with the user as `$user`. Either returns the
     * data in the shape of the type, with texts translated.
     *
     * @param  class-string<WidgetData>|Closure  $data
     */
    public function using(string|Closure $data): self
    {
        if (is_string($data) && ! is_subclass_of($data, WidgetData::class)) {
            throw new InvalidArgumentException(sprintf('The data of the widget "%s" is %s, which doesn\'t implement %s.', $this->id, $data, WidgetData::class));
        }
        if ($this->resource !== null) {
            throw new InvalidArgumentException(sprintf('The widget "%s" reads a resource: it can\'t also have data of its own.', $this->id));
        }
        $this->using = $data;

        return $this;
    }

    /**
     * Keeps a successful answer for some seconds, per user, locale and context: for a widget
     * that calls another service or computes something heavy. Errors are never kept. Don't cache
     * the items of a resource, which the user expects to see as soon as they change.
     */
    public function cache(int $seconds): self
    {
        if ($seconds < 1) {
            throw new InvalidArgumentException(sprintf('The widget "%s" is cached for %d seconds: give it at least one.', $this->id, $seconds));
        }
        $this->cache = $seconds;

        return $this;
    }

    /**
     * Shown only to the users who have an ability: a Gate ability, or a closure called through
     * the container with the user as `$user`. For the others, the widget isn't in the
     * definition, and its data is a `404`.
     *
     * @param  string|(Closure(?Authenticatable): bool)  $ability
     */
    public function can(string|Closure $ability): self
    {
        $this->ability = $ability;

        return $this;
    }

    /**
     * @internal Read by `Dashboard`.
     */
    public function getResource(): ?string
    {
        return $this->resource;
    }

    /**
     * @internal Read by `Dashboard`.
     *
     * @return array<string, scalar|list<scalar>>
     */
    public function getQuery(): array
    {
        return $this->query;
    }

    /**
     * @internal Read by `Dashboard`: what the widget measures of the aggregates of its resource.
     */
    public function getMeasure(): ?Measure
    {
        return $this->measure;
    }

    /**
     * @internal Read by `Dashboard`: how the widget groups the aggregates of its resource.
     */
    public function getGroup(): ?Group
    {
        return $this->group;
    }

    /**
     * @internal Read by `Dashboard`: `followsList()`, or null without it.
     */
    public function getFollowsList(): ?bool
    {
        return $this->followsList;
    }

    /**
     * @internal Read by `Dashboard`.
     */
    public function getTarget(): int|float|null
    {
        return $this->target;
    }

    /**
     * Whether the widget reads the aggregates of its resource, rather than its list: a `series`,
     * a `breakdown`, and a `stat` that sums.
     *
     * @internal Read by `Dashboard`.
     */
    public function readsAggregates(): bool
    {
        return in_array($this->type, self::GROUPED_TYPES, true)
            || ($this->type === 'stat' && $this->measure !== null && ! $this->measure->isCount());
    }

    /**
     * @internal Read by `Dashboard`.
     *
     * @return class-string<WidgetData>|Closure|null
     */
    public function getData(): string|Closure|null
    {
        return $this->using;
    }

    /**
     * @internal Read by `Dashboard`.
     */
    public function getCache(): ?int
    {
        return $this->cache;
    }

    /**
     * @internal Read by `Dashboard`.
     *
     * @return string|(Closure(?Authenticatable): bool)|null
     */
    public function ability(): string|Closure|null
    {
        return $this->ability;
    }

    /**
     * Throws for a widget that doesn't read aggregates: a `list`, or a widget of a plugin.
     */
    private function checkAggregates(string $method): void
    {
        if (! in_array($this->type, ['stat', ...self::GROUPED_TYPES], true)) {
            throw new InvalidArgumentException(sprintf('The %s "%s" can\'t have %s: only a stat, a series and a breakdown measure a resource.', $this->type, $this->id, $method));
        }
    }

    /**
     * The widget in the definition, with the id of its resource.
     *
     * @internal Built by `Dashboard`, which checks the resource.
     *
     * @return array<string, mixed>
     */
    public function toDefinition(?string $resource = null): array
    {
        $settings = $resource !== null
            ? array_filter([
                'resource' => $resource,
                'query' => $this->query !== [] ? $this->query : null,
                'measure' => $this->measure?->toParameter(),
                'group' => $this->group?->toParameter(),
                'target' => $this->target,
            ], fn (mixed $value): bool => $value !== null)
            : $this->settings;
        if ($this->followsList !== null) {
            $settings['followsList'] = $this->followsList;
        }

        return array_filter([
            'id' => $this->id,
            'type' => $this->type,
            'label' => $this->label,
            'width' => $this->width,
            // Left out when empty, so that it isn't sent as a JSON list
            'settings' => $settings !== [] ? $settings : null,
        ], fn (mixed $value): bool => $value !== null);
    }
}
