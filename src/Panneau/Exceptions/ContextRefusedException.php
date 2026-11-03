<?php

namespace Panneau\Exceptions;

use RuntimeException;

/**
 * A value of the context that the user can't use, or no value for a param a resource needs: a
 * `403` problem of type `urn:panneau:problem:context`, with the `param` (panneau-js decision
 * 0045).
 */
final class ContextRefusedException extends RuntimeException
{
    public function __construct(public readonly string $param)
    {
        parent::__construct(sprintf('The value of the context param "%s" can\'t be used.', $param));
    }
}
