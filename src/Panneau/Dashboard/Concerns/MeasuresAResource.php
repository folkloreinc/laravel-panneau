<?php

namespace Panneau\Dashboard\Concerns;

use InvalidArgumentException;
use Panneau\Aggregates\Measure;
use Panneau\Dashboard\WidgetQuery;

/**
 * What a source of a widget on several resources reads of the aggregates of its resource
 * (panneau-js decision 0089): a line of a `series` (`Source`) or a row of a `table` (`Row`).
 *
 * @internal
 */
trait MeasuresAResource
{
    private string $resource;

    private string $label;

    /**
     * @var array<string, scalar|list<scalar>>
     */
    private array $query = [];

    private ?Measure $measure = null;

    private string|false|null $period = null;

    /**
     * @var array{query: array<string, scalar|list<scalar>>, label: string, tone: string|null}|null
     */
    private ?array $alert = null;

    private function __construct(string $resource, string $label)
    {
        if ($resource === '') {
            throw new InvalidArgumentException(sprintf('The %s "%s" needs a resource.', self::NAME, $label));
        }
        if ($label === '') {
            throw new InvalidArgumentException(sprintf('The %s on "%s" needs a label.', self::NAME, $resource));
        }
        $this->resource = $resource;
        $this->label = $label;
    }

    /**
     * The query of the list of the resource, written as its query parameters: its filters and its
     * search (`['filter[status]' => 'sent']`).
     *
     * @param  array<string, mixed>  $query  Scalars, or lists of scalars.
     */
    public function query(array $query): self
    {
        $this->query = WidgetQuery::check($query, $this->name());

        return $this;
    }

    /**
     * Counts the items of the resource: the default.
     */
    public function count(): self
    {
        $this->measure = Measure::count();

        return $this;
    }

    /**
     * Sums a field of the items, among the `sum` of the aggregates of the list of the resource. A
     * sum of `money` is in minor units, written in its currency.
     */
    public function sum(string $field): self
    {
        $this->measure = Measure::sum($field);

        return $this;
    }

    /**
     * The id of the `date-range` filter of the list of the resource that gets the days of the
     * period of the overview: the `period()` of the widget by default. `false`: it doesn't follow
     * the period.
     */
    public function period(string|false $period): self
    {
        if ($period === '') {
            throw new InvalidArgumentException(sprintf('%s follows the period with the filter "": give the id of a date-range filter, or false.', ucfirst($this->name())));
        }
        $this->period = $period;

        return $this;
    }

    /**
     * The items that call for attention, counted with the query and the period of the source and
     * the filters of `$query` on top (`['filter[status]' => 'failed']`): shown as "4 failed", in
     * `$tone` (`danger` by default), when there are some.
     *
     * @param  array<string, mixed>  $query  Scalars, or lists of scalars.
     * @param  string  $label  What they are, written after their count, translated: `failed`.
     */
    public function alert(array $query, string $label, ?string $tone = null): self
    {
        if ($query === []) {
            throw new InvalidArgumentException(sprintf('The alert of %s counts some of its items: give it the filters of a query.', $this->name()));
        }
        if ($label === '') {
            throw new InvalidArgumentException(sprintf('The alert of %s needs a label: what its items are.', $this->name()));
        }
        if ($tone !== null && preg_match('/^[a-z0-9-]+$/', $tone) !== 1) {
            throw new InvalidArgumentException(sprintf('The alert of %s has the tone "%s": tones are names of the theme, such as danger.', $this->name(), $tone));
        }
        $this->alert = [
            'query' => WidgetQuery::check($query, 'the alert of '.$this->name()),
            'label' => $label,
            'tone' => $tone,
        ];

        return $this;
    }

    /**
     * @internal Read by `Dashboard`: the resource, a class or an id.
     */
    public function getResource(): string
    {
        return $this->resource;
    }

    /**
     * @internal Read by `Dashboard`.
     */
    public function getLabel(): string
    {
        return $this->label;
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
     * @internal Read by `Dashboard`.
     */
    public function getMeasure(): ?Measure
    {
        return $this->measure;
    }

    /**
     * @internal Read by `Dashboard`: `period()`, or null without it.
     */
    public function getPeriod(): string|false|null
    {
        return $this->period;
    }

    /**
     * @internal Read by `Dashboard`: the query of `alert()`, empty without one.
     *
     * @return array<string, scalar|list<scalar>>
     */
    public function getAlertQuery(): array
    {
        return $this->alert['query'] ?? [];
    }

    /**
     * The source in messages: `the source "Emails"`.
     */
    private function name(): string
    {
        return sprintf('the %s "%s"', self::NAME, $this->label);
    }

    /**
     * The keys that sources and rows share, in the definition.
     *
     * @return array<string, mixed>
     */
    private function sharedDefinition(string $resource): array
    {
        return array_filter([
            'resource' => $resource,
            'label' => $this->label,
            'query' => $this->query !== [] ? $this->query : null,
            'measure' => $this->measure?->toParameter(),
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * The period and the alert, in the definition.
     *
     * @return array<string, mixed>
     */
    private function periodAndAlert(): array
    {
        $definition = [];
        if ($this->period !== null) {
            $definition['period'] = $this->period;
        }
        if ($this->alert !== null) {
            $definition['alert'] = array_filter($this->alert, fn (mixed $value): bool => $value !== null);
        }

        return $definition;
    }
}
