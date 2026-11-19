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
 * A widget of a dashboard (panneau-js decisions 0064, 0084 and 0089): a `stat`, a `series`, a
 * `list`, a `breakdown`, a `metric` or a `table`, or a type that a JS plugin draws. The built-in
 * types can read a resource (`resource()`): a `stat` counts its list, a `list` shows its first
 * items, and a `series`, a `breakdown`, a `metric` and a `stat` that sums read its aggregates
 * (`per()`, `by()`, `sum()`, `points()`, `split()`). A `series` can read several resources
 * (`sources()`), and a `table` a row per resource (`rows()`). The others get their data from a
 * class or a closure (`using()`), which the app asks for at `GET {path}/api/dashboard/widgets/{id}`.
 *
 * The named constructors return the builder of their type (`StatWidget`, `SeriesWidget`,
 * `BreakdownWidget`, `ListWidget`, `MetricWidget`, `TableWidget`), which has the methods that
 * apply to it, public: `Widget::series('runs')->per('day', 'ran_at')`. A widget of a plugin
 * (`make()`) has the methods that all widgets share. The methods of the types are protected here,
 * and public on their builders. It isn't meant to be extended by projects.
 */
class Widget
{
    /**
     * The types that the app draws.
     */
    public const BUILT_IN_TYPES = ['stat', 'series', 'list', 'breakdown', 'metric', 'table'];

    /**
     * The built-in types that can read a resource: `stat` and `list` its list, `series`,
     * `breakdown` and `metric` its aggregates, and a `stat` that sums too.
     */
    public const RESOURCE_TYPES = ['stat', 'series', 'list', 'breakdown', 'metric'];

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
     * The most lines of the data of a `series`.
     */
    public const SERIES_MAX_SERIES = 6;

    /**
     * The most sources of a `series` on several resources (`sources()`).
     */
    public const SOURCES_MAX = 5;

    /**
     * The most rows of a `table`, of its data or on resources (`rows()`).
     */
    public const TABLE_MAX_ROWS = 8;

    /**
     * The most columns of numbers of the data of a `table`, after the column of the labels.
     */
    public const TABLE_MAX_COLUMNS = 6;

    /**
     * How the columns of the data of a `table` align their numbers: `end` by default.
     */
    public const TABLE_ALIGNS = ['start', 'end'];

    /**
     * Whether a rise or a fall of a `metric` or a `table` is good news (`goodWhen()`).
     */
    public const GOOD_WHEN = ['up', 'down'];

    /**
     * Query parameters that the app sets on the list of a widget on a resource.
     */
    public const RESERVED_QUERY_PARAMS = WidgetQuery::RESERVED_PARAMS;

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

    private string|false|null $period = null;

    private ?Group $points = null;

    private ?string $by = null;

    private ?string $split = null;

    /**
     * @var list<Source>
     */
    private array $sources = [];

    /**
     * @var list<Row>
     */
    private array $rows = [];

    /**
     * @var array<string, mixed>
     */
    private array $format = [];

    private bool $hideEmpty = false;

    private ?string $goodWhen = null;

    private bool $stacked = false;

    /**
     * @var class-string<WidgetData>|Closure|null
     */
    private string|Closure|null $using = null;

    private ?int $cache = null;

    /**
     * @var string|(Closure(?Authenticatable): bool)|null
     */
    private string|Closure|null $ability = null;

    final protected function __construct(private readonly string $id, private readonly string $type)
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
    public static function stat(string $id): StatWidget
    {
        return new StatWidget($id, 'stat');
    }

    /**
     * A line of values per day, week or month, with its total: the aggregates of a resource
     * (`resource()->per('day', 'created_at')`), or data of its own.
     */
    public static function series(string $id): SeriesWidget
    {
        return new SeriesWidget($id, 'series');
    }

    /**
     * The parts of a total, as horizontal bars, the largest first: the aggregates of a resource
     * by the values of a field (`resource()->by('status')`), or data of its own.
     */
    public static function breakdown(string $id): BreakdownWidget
    {
        return new BreakdownWidget($id, 'breakdown');
    }

    /**
     * A few items: the first of the list of a resource, or data of its own.
     */
    public static function list(string $id): ListWidget
    {
        return new ListWidget($id, 'list');
    }

    /**
     * One subject in one card (panneau-js decision 0089): a number, its change from the previous
     * period, its trend and its largest parts. On the aggregates of a resource
     * (`resource()->sum('cost')->points('day', 'created_at')->by('service')`), or data of its own.
     */
    public static function metric(string $id): MetricWidget
    {
        return new MetricWidget($id, 'metric');
    }

    /**
     * Rows of numbers, one per subject (panneau-js decision 0089): a row per resource, with its
     * count or its sum over the period, its change, its alert and its trend (`rows()`), or data of
     * its own.
     */
    public static function table(string $id): TableWidget
    {
        return new TableWidget($id, 'table');
    }

    /**
     * A widget of a type that a JS plugin draws, with data of its own. A built-in type gets its
     * builder, as its named constructor gives it (`make('a', 'series')` is a `SeriesWidget`).
     */
    public static function make(string $id, string $type): self
    {
        return match ($type) {
            'stat' => new StatWidget($id, $type),
            'series' => new SeriesWidget($id, $type),
            'breakdown' => new BreakdownWidget($id, $type),
            'list' => new ListWidget($id, $type),
            'metric' => new MetricWidget($id, $type),
            'table' => new TableWidget($id, $type),
            default => new self($id, $type),
        };
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
    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * The columns it takes in a grid of 12. Defaults to 3 for a `stat`, 4 for a `metric`, 6 for
     * the others.
     */
    public function width(int $width): static
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
    public function settings(array $settings): static
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
    protected function resource(string $resource, array $query = []): static
    {
        if (! in_array($this->type, self::RESOURCE_TYPES, true)) {
            throw new InvalidArgumentException(sprintf('The %s "%s" can\'t read a resource: only a stat, a series, a list, a breakdown and a metric can. Give it data with using().', $this->type, $this->id));
        }
        if ($this->using !== null) {
            throw new InvalidArgumentException(sprintf('The widget "%s" has data of its own (using()): it can\'t also read a resource.', $this->id));
        }
        if ($this->sources !== []) {
            throw new InvalidArgumentException(sprintf('The series "%s" reads the resources of its sources(): it can\'t also read a resource.', $this->id));
        }
        $this->query = WidgetQuery::check($query, sprintf('the widget "%s"', $this->id));
        $this->resource = $resource;

        return $this;
    }

    /**
     * Counts the items of the resource: a `stat` reads the total of its list, a `series` and a
     * `breakdown` count the items of each period or part. The default.
     */
    protected function count(): static
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
    protected function sum(string $field): static
    {
        $this->checkAggregates('sum()');
        $this->measure = Measure::sum($field);

        return $this;
    }

    /**
     * The periods of the points of a `series` on a resource: the days, weeks (from Monday) or
     * months of a date field, among the `dates` of the aggregates of its list.
     */
    protected function per(Interval|string $period, string $field): static
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
     * The field whose values are the parts of a `breakdown` or a `metric` on a resource, among
     * the `groupBy` of the aggregates of its list. A `select` gives the labels and the tones of
     * its options, a `relation` the titles of its items, and each bar links to the list filtered
     * by its value. A `metric` draws its 5 largest parts under its number.
     */
    protected function by(string $field): static
    {
        if ($this->type === 'metric') {
            if ($field === '') {
                throw new InvalidArgumentException(sprintf('The metric "%s" has its parts by "": give the field of their values.', $this->id));
            }
            $this->by = $field;

            return $this;
        }
        if ($this->type !== 'breakdown') {
            throw new InvalidArgumentException(sprintf('The %s "%s" has no parts: only a breakdown and a metric do.', $this->type, $this->id));
        }
        $this->group = Group::field($field);

        return $this;
    }

    /**
     * The points of the trend of a `metric` on a resource, drawn as a small line: the days, weeks
     * (from Monday) or months of a date field, among the `dates` of the aggregates of its list.
     * On a `table`, the trend of its rows, unless they have their own.
     */
    protected function points(Interval|string $period, string $field): static
    {
        if (! in_array($this->type, ['metric', 'table'], true)) {
            throw new InvalidArgumentException(sprintf('The %s "%s" has no trend: only a metric and a table do.', $this->type, $this->id));
        }
        if (! $period instanceof Interval && Interval::tryFrom($period) === null) {
            throw new InvalidArgumentException(sprintf('The %s "%s" has points per %s: periods are day, week or month.', $this->type, $this->id, json_encode($period)));
        }
        $this->points = Group::period($period, $field);

        return $this;
    }

    /**
     * A line per value of a field of a `series` on a resource (panneau-js decision 0089): the
     * field, among the `groupBy` of the aggregates of its list, needs a filter of the list, which
     * reads each line. The app draws the 5 largest values, and the rest as "Other".
     */
    protected function split(string $field): static
    {
        if ($this->type !== 'series') {
            throw new InvalidArgumentException(sprintf('The %s "%s" has no lines: only a series is split.', $this->type, $this->id));
        }
        if ($field === '') {
            throw new InvalidArgumentException(sprintf('The series "%s" is split by "": give the field of its lines.', $this->id));
        }
        $this->split = $field;

        return $this;
    }

    /**
     * The lines of a `series` on several resources (panneau-js decision 0089), at most 5: a
     * `Source` each, on a resource of its own. The series has no `resource()`; its `per()` and
     * `period()` are those of the sources without their own.
     *
     * @param  list<Source>  $sources
     */
    protected function sources(array $sources): static
    {
        if ($this->type !== 'series') {
            throw new InvalidArgumentException(sprintf('The %s "%s" has no sources: only a series does. A table has rows().', $this->type, $this->id));
        }
        if ($this->resource !== null) {
            throw new InvalidArgumentException(sprintf('The series "%s" reads a resource: it can\'t also have sources().', $this->id));
        }
        $this->sources = $this->checkSources($sources, Source::class, self::SOURCES_MAX, 'sources');

        return $this;
    }

    /**
     * The rows of a `table` on resources (panneau-js decision 0089), at most 8: a `Row` each, the
     * count or the sum of a resource over the period, with its change, its alert and its trend.
     *
     * @param  list<Row>  $rows
     */
    protected function rows(array $rows): static
    {
        if ($this->type !== 'table') {
            throw new InvalidArgumentException(sprintf('The %s "%s" has no rows: only a table does. A series has sources().', $this->type, $this->id));
        }
        $this->rows = $this->checkSources($rows, Row::class, self::TABLE_MAX_ROWS, 'rows');

        return $this;
    }

    /**
     * A target for the number of a `stat` or a `metric` on a resource (a budget, a quota), drawn
     * as a meter under it, in the unit of its value (minor units for a sum of `money`). Past it,
     * the meter is `danger`.
     */
    protected function target(int|float $target): static
    {
        if (! in_array($this->type, ['stat', 'metric'], true)) {
            throw new InvalidArgumentException(sprintf('The %s "%s" has no target: only a stat and a metric do.', $this->type, $this->id));
        }
        if ($target <= 0) {
            throw new InvalidArgumentException(sprintf('The %s "%s" has the target %s: targets are above 0.', $this->type, $this->id, json_encode($target)));
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
    public function followsList(bool $follows = true): static
    {
        $this->followsList = $follows;

        return $this;
    }

    /**
     * How the widget follows the period of its overview (panneau-js decision 0084). On a
     * resource: the id of the list's `date-range` filter that gets the days of the chosen period
     * (`'created_at'`); without it, only the widgets of a summary that follow its list do. `false`:
     * the widget doesn't follow the period; a widget with data of its own then gets none in its
     * `WidgetRequest`.
     */
    public function period(string|false $period): static
    {
        if ($period === '') {
            throw new InvalidArgumentException(sprintf('The widget "%s" follows the period with the filter "": give the id of a date-range filter, or false.', $this->id));
        }
        $this->period = $period;

        return $this;
    }

    /**
     * How the numbers of a built-in widget are written (panneau-js decision 0089): options of
     * `Intl.NumberFormat`, in the locale of the user. `style` (`decimal`, `percent`, `currency`
     * with `currency`, an ISO 4217 code, or `unit` with `unit`), `currencyDisplay`, `unit` (a
     * unit of ECMA-402, `megabyte`, `second`…, or two joined by `-per-`), `unitDisplay`,
     * `minimumFractionDigits`, `maximumFractionDigits` (0 to 20), `notation` (`compact`) and
     * `signDisplay`. Merged over the options set before, key by key (null removes one), and over
     * the `format` of the data by the app. Without a format, a sum of `money` on a resource is
     * written in its currency.
     *
     * @param  array<string, mixed>  $options
     */
    protected function format(array $options): static
    {
        $this->checkBuiltIn('format()');
        $unknown = array_diff(array_keys($options), WidgetFormat::KEYS);
        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf('The format of the widget "%s" has the option "%s": formats take %s.', $this->id, reset($unknown), implode(', ', WidgetFormat::KEYS)));
        }
        $format = array_filter([...$this->format, ...$options], fn (mixed $value): bool => $value !== null);
        $wrong = WidgetFormat::errors($format);
        if ($wrong !== []) {
            throw new InvalidArgumentException(sprintf('The format of the widget "%s" has a wrong %s: %s.', $this->id, $wrong[0], json_encode($format)));
        }
        $this->format = $format;

        return $this;
    }

    /**
     * Writes the numbers as amounts of a currency, an ISO 4217 code (`USD`): `$9.08`.
     *
     * @param  string|null  $display  `symbol` (the default), `narrowSymbol`, `code` or `name`.
     */
    protected function currency(string $currency, ?string $display = null): static
    {
        return $this->format(['style' => 'currency', 'currency' => $currency, 'currencyDisplay' => $display, 'unit' => null, 'unitDisplay' => null]);
    }

    /**
     * Writes the numbers in a unit of `Intl.NumberFormat` (`megabyte`, `second`,
     * `kilometer-per-hour`): `1,530.3 MB`. The value is in that unit: it isn't converted.
     *
     * @param  string|null  $display  `short` (the default), `narrow` or `long`.
     */
    protected function unit(string $unit, ?string $display = null): static
    {
        return $this->format(['style' => 'unit', 'unit' => $unit, 'unitDisplay' => $display, 'currency' => null, 'currencyDisplay' => null]);
    }

    /**
     * Writes the numbers as percents: `0.12` is `12%`.
     */
    protected function percent(): static
    {
        return $this->format(['style' => 'percent', 'currency' => null, 'currencyDisplay' => null, 'unit' => null, 'unitDisplay' => null]);
    }

    /**
     * Hides a built-in widget whose data has nothing to show, as a `403` does, and the grid closes
     * the gap (panneau-js decision 0089): a `stat` at 0, a `metric` at 0 that was 0, a `series`
     * whose points are all 0, a `breakdown` whose parts are all 0, a `list` without items, a
     * `table` without rows.
     */
    protected function hideEmpty(bool $hide = true): static
    {
        $this->checkBuiltIn('hideEmpty()');
        $this->hideEmpty = $hide;

        return $this;
    }

    /**
     * Whether a rise (`up`) or a fall (`down`) of a `metric` or a `table` is good news: its change
     * from the previous period is then `success` that way, and `danger` the other way. Neutral
     * without it.
     */
    protected function goodWhen(string $direction): static
    {
        if (! in_array($this->type, ['metric', 'table'], true)) {
            throw new InvalidArgumentException(sprintf('The %s "%s" shows no change: only a metric and a table have goodWhen().', $this->type, $this->id));
        }
        if (! in_array($direction, self::GOOD_WHEN, true)) {
            throw new InvalidArgumentException(sprintf('The %s "%s" is good when %s: give up or down.', $this->type, $this->id, json_encode($direction)));
        }
        $this->goodWhen = $direction;

        return $this;
    }

    /**
     * Stacks the lines of a `series` with several, as areas.
     */
    protected function stacked(bool $stacked = true): static
    {
        if ($this->type !== 'series') {
            throw new InvalidArgumentException(sprintf('The %s "%s" has no lines to stack: only a series does.', $this->type, $this->id));
        }
        $this->stacked = $stacked;

        return $this;
    }

    /**
     * The data of the widget: a class that implements `WidgetData`, built from the container,
     * or a closure called through the container with the user as `$user`. Either returns the
     * data in the shape of the type, with texts translated.
     *
     * @param  class-string<WidgetData>|Closure  $data
     */
    public function using(string|Closure $data): static
    {
        if (is_string($data) && ! is_subclass_of($data, WidgetData::class)) {
            throw new InvalidArgumentException(sprintf('The data of the widget "%s" is %s, which doesn\'t implement %s.', $this->id, $data, WidgetData::class));
        }
        if ($this->resource !== null) {
            throw new InvalidArgumentException(sprintf('The widget "%s" reads a resource: it can\'t also have data of its own.', $this->id));
        }
        if ($this->sources !== [] || $this->rows !== []) {
            throw new InvalidArgumentException(sprintf('The %s "%s" reads the resources of its %s: it can\'t also have data of its own.', $this->type, $this->id, $this->type === 'table' ? 'rows()' : 'sources()'));
        }
        $this->using = $data;

        return $this;
    }

    /**
     * Keeps a successful answer for some seconds, per user, locale and context: for a widget
     * that calls another service or computes something heavy. Errors are never kept. Don't cache
     * the items of a resource, which the user expects to see as soon as they change.
     */
    public function cache(int $seconds): static
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
    public function can(string|Closure $ability): static
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
     * @internal Read by `Dashboard`: `period()`, or null without it.
     */
    public function getPeriod(): string|false|null
    {
        return $this->period;
    }

    /**
     * @internal Read by `Dashboard`.
     */
    public function getTarget(): int|float|null
    {
        return $this->target;
    }

    /**
     * @internal Read by `Dashboard`: `points()`, or null without it.
     */
    public function getPoints(): ?Group
    {
        return $this->points;
    }

    /**
     * @internal Read by `Dashboard`: the field of the parts of a `metric`, or null.
     */
    public function getBy(): ?string
    {
        return $this->by;
    }

    /**
     * @internal Read by `Dashboard`: `split()`, or null without it.
     */
    public function getSplit(): ?string
    {
        return $this->split;
    }

    /**
     * @internal Read by `Dashboard`: the sources of a `series`, or the rows of a `table`.
     *
     * @return list<Source>|list<Row>
     */
    public function getSources(): array
    {
        return $this->type === 'table' ? $this->rows : $this->sources;
    }

    /**
     * Whether the widget reads the aggregates of its resource, rather than its list: a `series`,
     * a `breakdown`, a `metric`, and a `stat` that sums.
     *
     * @internal Read by `Dashboard`.
     */
    public function readsAggregates(): bool
    {
        return in_array($this->type, [...self::GROUPED_TYPES, 'metric'], true)
            || ($this->type === 'stat' && $this->measure !== null && ! $this->measure->isCount());
    }

    /**
     * Whether the widget reads resources rather than data of its own, as `readsResources()` of
     * `@panneau/protocol`: a `resource()`, `sources()` or `rows()`. It has no data at the widget
     * endpoint.
     *
     * @internal Read by `Dashboard` and the widget endpoint.
     */
    public function readsResources(): bool
    {
        return $this->resource !== null || $this->sources !== [] || $this->rows !== [];
    }

    /**
     * Whether the widget measures a resource: what only a widget with a `resource()` has.
     *
     * @internal Read by `Dashboard`.
     */
    public function measuresResource(): bool
    {
        return $this->measure !== null || $this->group !== null || $this->target !== null
            || $this->points !== null || $this->by !== null || $this->split !== null;
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
        if (! in_array($this->type, ['stat', ...self::GROUPED_TYPES, 'metric'], true)) {
            throw new InvalidArgumentException(sprintf('The %s "%s" can\'t have %s: only a stat, a series, a breakdown and a metric measure a resource. The sources of a series and the rows of a table measure theirs.', $this->type, $this->id, $method));
        }
    }

    /**
     * Throws for a widget of a plugin, whose settings are its own (`settings()`).
     */
    private function checkBuiltIn(string $method): void
    {
        if (! in_array($this->type, self::BUILT_IN_TYPES, true)) {
            throw new InvalidArgumentException(sprintf('The %s "%s" can\'t have %s: give a widget of a plugin its settings().', $this->type, $this->id, $method));
        }
    }

    /**
     * Checks the sources of a `series` or the rows of a `table`: instances of their class, from 1
     * to `$max`.
     *
     * @template T of Source|Row
     *
     * @param  array<array-key, mixed>  $sources
     * @param  class-string<T>  $class
     * @return list<T>
     */
    private function checkSources(array $sources, string $class, int $max, string $method): array
    {
        if ($this->using !== null) {
            throw new InvalidArgumentException(sprintf('The %s "%s" has data of its own (using()): it can\'t also have %s().', $this->type, $this->id, $method));
        }
        if ($sources === [] || count($sources) > $max) {
            throw new InvalidArgumentException(sprintf('The %s "%s" has %d %s: give it from 1 to %d.', $this->type, $this->id, count($sources), $method, $max));
        }
        $checked = [];
        foreach ($sources as $source) {
            if (! $source instanceof $class) {
                throw new InvalidArgumentException(sprintf('The %s "%s" has a %s in its %s(): give it %s builders.', $this->type, $this->id, get_debug_type($source), $method, $class));
            }
            $checked[] = $source;
        }

        return $checked;
    }

    /**
     * The widget in the definition, with the id of its resource, or of the resources of its
     * sources or rows, in order.
     *
     * @internal Built by `Dashboard`, which checks the resources.
     *
     * @param  list<string>  $sources  The ids of the resources of `sources()` or `rows()`: the
     *                                 names they were given without them.
     * @return array<string, mixed>
     */
    public function toDefinition(?string $resource = null, array $sources = []): array
    {
        $notNull = fn (mixed $value): bool => $value !== null;
        $settings = match (true) {
            $resource !== null => array_filter([
                'resource' => $resource,
                'query' => $this->query !== [] ? $this->query : null,
                'measure' => $this->measure?->toParameter(),
                'group' => $this->group?->toParameter(),
                'target' => $this->target,
                'points' => $this->points?->toParameter(),
                'by' => $this->by,
                'split' => $this->split,
            ], $notNull),
            $this->sources !== [] || $this->rows !== [] => array_filter([
                // The defaults of the sources, or of the rows
                'group' => $this->group?->toParameter(),
                'points' => $this->points?->toParameter(),
                $this->type === 'table' ? 'rows' : 'sources' => array_map(
                    fn (Source|Row $source, int $index): array => $source->toDefinition($sources[$index] ?? $source->getResource()),
                    $this->getSources(),
                    array_keys($this->getSources()),
                ),
            ], $notNull),
            default => $this->settings,
        };
        $settings = [...$settings, ...array_filter([
            'format' => $this->format !== [] ? $this->format : null,
            'hideEmpty' => $this->hideEmpty ? true : null,
            'goodWhen' => $this->goodWhen,
            'stacked' => $this->stacked ? true : null,
        ], $notNull)];
        if ($this->followsList !== null) {
            $settings['followsList'] = $this->followsList;
        }
        if ($this->period !== null) {
            $settings['period'] = $this->period;
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
