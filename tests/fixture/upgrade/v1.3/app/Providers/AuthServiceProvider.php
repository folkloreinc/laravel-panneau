<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        \Panneau\Panneau::class => \App\Panneau\Policies\PanneauPolicy::class,
    ];
}
