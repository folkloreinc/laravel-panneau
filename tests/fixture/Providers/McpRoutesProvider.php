<?php

namespace TestApp\Providers;

use Illuminate\Support\ServiceProvider;
use Panneau\Support\Facade as Panneau;

/**
 * A provider of the project that loads the routes of Panneau in its boot(), before Passport's
 * own provider has registered the routes of the authorization.
 */
class McpRoutesProvider extends ServiceProvider
{
    public function boot(): void
    {
        Panneau::mcpRoutes();
    }
}
