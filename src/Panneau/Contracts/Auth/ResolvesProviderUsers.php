<?php

namespace Panneau\Contracts\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Socialite\Contracts\User as ProviderUser;

/**
 * Finds, links or creates the user of a login with an OAuth provider (panneau-js decision 0052),
 * set in `panneau.auth.provider_users`. Without it, the user is the one whose email is the
 * provider's verified address, and nobody is created.
 */
interface ResolvesProviderUsers
{
    /**
     * @param  string  $provider  The id of the provider in `panneau.auth.providers`.
     * @param  ProviderUser  $user  What the provider says of the user, whose email was checked.
     * @param  Authenticatable|null  $found  The user with this email, through the user provider of the guard.
     * @return Authenticatable|null The user to log in, or null to refuse the login.
     */
    public function resolve(string $provider, ProviderUser $user, ?Authenticatable $found): ?Authenticatable;
}
