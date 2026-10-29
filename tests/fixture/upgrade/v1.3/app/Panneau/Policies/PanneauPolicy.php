<?php

namespace App\Panneau\Policies;

class PanneauPolicy
{
    public function view($user)
    {
        return $user->is_admin;
    }
}
