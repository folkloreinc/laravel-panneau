<?php

namespace Panneau\Exceptions;

use RuntimeException;

/**
 * An update sent an `If-Match` that doesn't match the item's version.
 */
final class VersionConflictException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $current  The current item, serialized.
     */
    public function __construct(public readonly array $current)
    {
        parent::__construct('The item was changed since it was loaded.');
    }
}
