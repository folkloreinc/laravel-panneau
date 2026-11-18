<?php

namespace Panneau\Dashboard;

use InvalidArgumentException;
use Panneau\Aggregates\Group;
use Panneau\Aggregates\Interval;
use Panneau\Dashboard\Concerns\MeasuresAResource;

/**
 * A row of a `table` on resources (panneau-js decision 0089), in `Widget::table()->rows([...])`:
 * one subject, the count or the sum of a resource over the period, with its change from the
 * previous period, its alert ("6 rejected") and its trend.
 *
 * ```php
 * Row::make(EmailsResource::class, __('Emails'))
 *     ->alert(['filter[status]' => 'failed'], __('failed'));
 * ```
 */
final class Row
{
    use MeasuresAResource;

    private const NAME = 'row';

    private ?Group $points = null;

    /**
     * A row on a resource, a class or an id, with its label, translated.
     */
    public static function make(string $resource, string $label): self
    {
        return new self($resource, $label);
    }

    /**
     * The points of its trend: the days, weeks (from Monday) or months of a date field, among the
     * `dates` of the aggregates of the list of its resource. The `points()` of the table by
     * default. The trends of a table share one period.
     */
    public function points(Interval|string $period, string $field): self
    {
        if (! $period instanceof Interval && Interval::tryFrom($period) === null) {
            throw new InvalidArgumentException(sprintf('The row "%s" has points per %s: periods are day, week or month.', $this->label, json_encode($period)));
        }
        $this->points = Group::period($period, $field);

        return $this;
    }

    /**
     * @internal Read by `Dashboard`: `points()`, or null without it.
     */
    public function getGroup(): ?Group
    {
        return $this->points;
    }

    /**
     * The row in the definition, with the id of its resource.
     *
     * @internal Built by `Widget`.
     *
     * @return array<string, mixed>
     */
    public function toDefinition(string $resource): array
    {
        return [
            ...$this->sharedDefinition($resource),
            ...($this->points !== null ? ['points' => $this->points->toParameter()] : []),
            ...$this->periodAndAlert(),
        ];
    }
}
