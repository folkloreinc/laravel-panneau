<?php

namespace App\Panneau\Policies;

use App\Models\User;

class PanneauPolicy
{
    public function view(User $user)
    {
        return $user->role() === 'admin';
    }
}
