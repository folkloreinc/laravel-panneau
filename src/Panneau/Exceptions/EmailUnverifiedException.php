<?php

namespace Panneau\Exceptions;

use RuntimeException;

/**
 * A user whose email address isn't verified, with `panneau.auth.email_verification`: a `403`
 * problem of type `urn:panneau:problem:email-unverified` (panneau-js decision 0048).
 */
final class EmailUnverifiedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Your email address is not verified.');
    }
}
