<?php

namespace Panneau\Exceptions;

use RuntimeException;

/**
 * A user who must have two-factor authentication and hasn't set it up: a `403` problem of type
 * `urn:panneau:problem:two-factor-required` (panneau-js decision 0049).
 */
final class TwoFactorRequiredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Two-factor authentication is required for this account.');
    }
}
