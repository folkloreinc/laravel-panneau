<?php

namespace Panneau\Exceptions;

use RuntimeException;

/**
 * The data of a widget of the dashboard doesn't have the shape of its type: a `500`, reported
 * with the paths of the wrong keys, never with the data (panneau-js decision 0064).
 */
final class WidgetDataException extends RuntimeException
{
    /**
     * @param  list<string>  $keys  The paths of the wrong keys, such as `points.2.date`; `''` for
     *                              data that isn't an object.
     */
    public function __construct(public readonly string $widget, public readonly string $type, public readonly array $keys)
    {
        parent::__construct(sprintf(
            'The data of the dashboard widget "%s" doesn\'t have the shape of "%s": wrong %s.',
            $widget,
            $type,
            implode(', ', array_map(fn (string $key): string => $key === '' ? '(the data is not an object)' : $key, $keys)),
        ));
    }
}
