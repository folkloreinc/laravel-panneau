<?php

namespace Panneau\Roles;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A resource that says which of its items a user owns (decision 0014). For users whose roles are
 * all `owned`, the owner scope joins the scope of every operation, and `ResourcePolicy` checks it
 * on each item. An owned user is refused on a resource that doesn't implement it.
 */
interface OwnedResource
{
    /**
     * The items the user owns, as a scope: `['client_id' => $user->client_id]`, or a list of
     * accepted values (`['contest_id' => [3, 4]]`). `[]` for a resource owned users see in full.
     *
     * @return array<string, mixed>
     */
    public function ownerScope(Authenticatable $user): array;
}
