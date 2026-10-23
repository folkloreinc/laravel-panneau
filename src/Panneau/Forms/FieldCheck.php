<?php

namespace Panneau\Forms;

use Closure;

/**
 * A value to check, as it is typed, for a field with `check`: what
 * `POST /resources/{resource}/fields/{field}/check` received, and a way to know whether another
 * item has the value.
 */
final readonly class FieldCheck
{
    /**
     * @param  string  $value  The value, as typed. Not cleaned up.
     * @param  string|null  $locale  The locale of the value, for a localized field. Null otherwise.
     * @param  string|null  $id  The item being edited, or null when creating one.
     * @param  array<string, mixed>  $values  The form's values, keyed by field name, as they would be
     *                                        saved (relations as ids): the values the client sent,
     *                                        over those of the item being edited. Not validated.
     * @param  Closure(string, ?Closure): bool  $exists  Whether another item of the resource, within its scope
     *                                                   and in the value's locale, has a value.
     */
    public function __construct(
        public string $value,
        public ?string $locale,
        public ?string $id,
        public array $values,
        private Closure $exists,
    ) {}

    /**
     * Whether another item of the resource, within its scope and in the value's locale, has
     * this value. The closure narrows the query of an `EloquentRepository` further: it receives
     * the query and the form's values.
     */
    public function exists(string $value, ?Closure $constrain = null): bool
    {
        return ($this->exists)($value, $constrain);
    }
}
