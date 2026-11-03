<?php

namespace Panneau\Exceptions;

use RuntimeException;

/**
 * An operation that needs the password, which wasn't confirmed recently: a `423` problem of type
 * `urn:panneau:problem:password-confirmation` (panneau-js decision 0049).
 */
final class PasswordConfirmationRequiredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Password confirmation required.');
    }
}
