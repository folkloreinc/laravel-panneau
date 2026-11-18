<?php

namespace Panneau\Dashboard;

use InvalidArgumentException;
use Panneau\Aggregates\Group;
use Panneau\Aggregates\Interval;
use Panneau\Dashboard\Concerns\MeasuresAResource;

/**
 * A line of a `series` on several resources (panneau-js decision 0089), in
 * `Widget::series()->sources([...])`: the count or the sum of a resource per day, week or month,
 * possibly with an alert, "4 failed".
 *
 * ```php
 * Source::make(EmailsResource::class, __('Emails'))
 *     ->alert(['filter[status]' => 'failed'], __('failed'));
 * ```
 */
final class Source
{
    use MeasuresAResource;

    private const NAME = 'source';

    private ?Group $group = null;

    private ?string $help = null;

    private ?string $tone = null;

    /**
     * A line on a resource, a class or an id, with its label in the legend, translated.
     */
    public static function make(string $resource, string $label): self
    {
        return new self($resource, $label);
    }

    /**
     * The periods of its points: the days, weeks (from Monday) or months of a date field, among
     * the `dates` of the aggregates of the list of its resource. The `per()` of the series by
     * default. The lines of a series share one period.
     */
    public function per(Interval|string $period, string $field): self
    {
        if (! $period instanceof Interval && Interval::tryFrom($period) === null) {
            throw new InvalidArgumentException(sprintf('The source "%s" is per %s: periods are day, week or month.', $this->label, json_encode($period)));
        }
        $this->group = Group::period($period, $field);

        return $this;
    }

    /**
     * A line under its label in the legend, translated. An alert with items replaces it.
     */
    public function help(string $help): self
    {
        $this->help = $help;

        return $this;
    }

    /**
     * The tone of its line, a name of the theme (`success`), instead of a color of the charts.
     */
    public function tone(string $tone): self
    {
        if (preg_match('/^[a-z0-9-]+$/', $tone) !== 1) {
            throw new InvalidArgumentException(sprintf('The source "%s" has the tone "%s": tones are names of the theme, such as success.', $this->label, $tone));
        }
        $this->tone = $tone;

        return $this;
    }

    /**
     * @internal Read by `Dashboard`: `per()`, or null without it.
     */
    public function getGroup(): ?Group
    {
        return $this->group;
    }

    /**
     * The source in the definition, with the id of its resource.
     *
     * @internal Built by `Widget`.
     *
     * @return array<string, mixed>
     */
    public function toDefinition(string $resource): array
    {
        return [
            ...$this->sharedDefinition($resource),
            ...array_filter([
                'group' => $this->group?->toParameter(),
                'help' => $this->help,
                'tone' => $this->tone,
            ], fn (mixed $value): bool => $value !== null),
            ...$this->periodAndAlert(),
        ];
    }
}
