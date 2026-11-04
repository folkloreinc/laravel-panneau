<?php

namespace Panneau\Fields;

use Closure;

/**
 * The sessions of a performance or a screening (panneau-js decision 0053): a `repeater` whose
 * blocks have `dates`, a `DateRange` with `time()`, and, with `venues()`, `venue`, a relation to
 * the resource of the venues. Not a type of its own: `append()` adds fields to each session (a
 * price, a link to the tickets), and the `daterange` rule applies to each one.
 *
 * ```php
 * Sessions::make('sessions')->venues(VenuesResource::class)->append([Money::make('price')->currency('CAD')]);
 * ```
 *
 * Sessions are kept as the repeater's value (a JSON column with the `array` cast), or in rows of
 * their own by the project's repository.
 */
class Sessions extends Repeater
{
    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->fields = [DateRange::make('dates')->time()];
    }

    /**
     * Adds `venue`, a relation to the resource of the venues (its id or class), after `dates`.
     */
    public function venues(string $resource): static
    {
        $venue = Relation::make('venue')->resource($resource);
        $fields = array_values(array_filter($this->fields, fn (Field $field): bool => $field->name() !== 'venue'));
        $index = array_search('dates', array_map(fn (Field $field): string => $field->name(), $fields), true);
        array_splice($fields, $index === false ? 0 : $index + 1, 0, [$venue]);
        $this->fields = $fields;

        return $this;
    }

    /**
     * Changes the `dates` field of the sessions: `->dates(fn (DateRange $dates) => $dates->allDay())`.
     *
     * @param  Closure(DateRange): mixed  $configure
     */
    public function dates(Closure $configure): static
    {
        foreach ($this->fields as $field) {
            if ($field instanceof DateRange && $field->name() === 'dates') {
                $configure($field);
            }
        }

        return $this;
    }

    /**
     * Adds fields to each session, after `dates` and `venue`.
     *
     * @param  list<Field>  $fields
     */
    public function append(array $fields): static
    {
        $this->fields = [...$this->fields, ...$fields];

        return $this;
    }
}
