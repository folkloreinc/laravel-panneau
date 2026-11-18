<?php

namespace Panneau\Dashboard;

use InvalidArgumentException;

/**
 * The query of a widget, of a source or of an alert on a resource: its query parameters, without
 * those the app sets.
 *
 * @internal
 */
final class WidgetQuery
{
    /**
     * Query parameters that the app sets on the list of a widget on a resource.
     */
    public const RESERVED_PARAMS = ['page', 'perPage', 'cursor'];

    /**
     * Checks a query: scalars, or lists of scalars, and none of the reserved parameters.
     *
     * @param  array<array-key, mixed>  $query
     * @param  string  $owner  What the query is of, in the messages: `the widget "drafts"`.
     * @return array<string, scalar|list<scalar>>
     *
     * @throws InvalidArgumentException
     */
    public static function check(array $query, string $owner): array
    {
        $checked = [];
        foreach ($query as $key => $value) {
            $key = (string) $key;
            if (in_array($key, self::RESERVED_PARAMS, true)) {
                throw new InvalidArgumentException(sprintf('The query of %s sets "%s", which the app sets.', $owner, $key));
            }
            if (is_scalar($value)) {
                $checked[$key] = $value;

                continue;
            }
            if (! is_array($value) || ! array_is_list($value) || count(array_filter($value, is_scalar(...))) !== count($value)) {
                throw new InvalidArgumentException(sprintf('The query of %s has a value of "%s" that isn\'t a scalar or a list of them.', $owner, $key));
            }
            /** @var list<scalar> $value */
            $checked[$key] = $value;
        }

        return $checked;
    }
}
