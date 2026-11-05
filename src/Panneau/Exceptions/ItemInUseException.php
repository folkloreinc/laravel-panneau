<?php

namespace Panneau\Exceptions;

use RuntimeException;
use Throwable;

/**
 * A repository can't delete an item because something else uses it: a media that a model uses,
 * for example. Nothing was deleted.
 *
 * `delete` reports the item as skipped with the reason `in_use`, and deletes the others of its
 * selection; a `DELETE` of the item answers a `409` problem. A repository throws it from
 * `delete()`, before it changes anything.
 */
final class ItemInUseException extends RuntimeException
{
    public function __construct(
        public readonly object $item,
        string $message = 'The item is in use: remove it from where it is used before deleting it.',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
