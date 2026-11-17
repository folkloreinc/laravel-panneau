<?php

namespace Panneau\Dashboard;

use InvalidArgumentException;

/**
 * One period for the widgets of an overview (panneau-js decision 0084): the periods a dashboard
 * page or a summary offers, chosen in its header and kept in the URL (`?period=30d`). Its days,
 * inclusive, end today in the time zone of the browser:
 *
 * - `7d`, `30d` and `90d`: the 7, 30 or 90 days ending today;
 * - `month`: the current calendar month, from its first day to today;
 * - `year`: the last 12 months, from the first day of the month 11 months ago to today.
 *
 * Declared with `Page::dashboard()->period()` and `Summary::period()`.
 */
final readonly class Period
{
    /**
     * The periods an overview can offer.
     */
    public const OPTIONS = ['7d', '30d', '90d', 'month', 'year'];

    /**
     * @param  list<string>  $options  The periods the user chooses from, in order.
     * @param  string|null  $default  The period the overview opens on, among `$options`: the first
     *                                one without it.
     * @param  string|null  $filter  In a summary: the id of the list's `date-range` filter that the
     *                               period sets, the first one without it.
     */
    public function __construct(
        public array $options,
        public ?string $default = null,
        public ?string $filter = null,
    ) {
        if ($options === []) {
            throw new InvalidArgumentException('A period needs options, among '.implode(', ', self::OPTIONS).'.');
        }
        foreach ($options as $option) {
            if (! in_array($option, self::OPTIONS, true)) {
                throw new InvalidArgumentException(sprintf('The period %s isn\'t one of %s.', json_encode($option), implode(', ', self::OPTIONS)));
            }
        }
        if (count(array_unique($options)) !== count($options)) {
            throw new InvalidArgumentException('Each period is offered once.');
        }
        if ($default !== null && ! in_array($default, $options, true)) {
            throw new InvalidArgumentException(sprintf('The default period %s isn\'t one of the options.', json_encode($default)));
        }
        if ($filter === '') {
            throw new InvalidArgumentException('The filter of a period is the id of a date-range filter.');
        }
    }

    /**
     * The period in the definition.
     *
     * @return array<string, mixed>
     */
    public function toDefinition(): array
    {
        return array_filter([
            'options' => $this->options,
            'default' => $this->default,
            'filter' => $this->filter,
        ], fn (mixed $value): bool => $value !== null);
    }
}
