<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class PanneauServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(\Folklore\Panneau\Contracts\Page::class, \App\Models\Page::class);
        $this->app->bind(\Folklore\Panneau\Contracts\Block::class, \App\Models\Block::class);

        $this->app->resolving('panneau', function ($panneau, $app) {
            $panneau->setLocales(config('locale.locales', [config('app.locale')]));
        });
    }
}
