<?php

namespace TestApp\Passport;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Passport\Client;

/**
 * A client model that skips the consent for every client, the ones that registered themselves
 * included.
 */
class TrustingClient extends Client
{
    public function skipsAuthorization(Authenticatable $user, array $scopes): bool
    {
        return true;
    }
}
