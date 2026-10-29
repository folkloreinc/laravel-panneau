<?php

namespace App\Panneau;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Facades\Gate;
use Panneau\Fields\Upload as UploadField;
use Panneau\Support\LocalizedField;
use Panneau\Support\Facade as Panneau;
use Folklore\Panneau\Fields\PageSlug as PageSlugField;

class PanneauServiceProvider extends BaseServiceProvider
{
    public function register()
    {
        Gate::policy(\Panneau\Panneau::class, \App\Panneau\Policies\PanneauPolicy::class);
    }

    public function boot()
    {
        $this->app->booted(function () {
            LocalizedField::setLocalesResolver(function () {
                return config('locale.locales');
            });

            UploadField::setEndpoint(route('panneau.upload'));

            PageSlugField::setRoutesResolver(function ($locale) {
                return ['page' => route($locale.'.page', ['page' => ':page'])];
            });
        });

        $view = $this->app[ViewFactory::class];
        $view->composer('panneau::app', \App\Panneau\Composers\AppComposer::class);

        Panneau::serving(function () use ($view) {
            $view->composer('errors::*', \Panneau\Composers\PanneauComposer::class);
            $view->composer('errors::*', \App\Panneau\Composers\AppComposer::class);
        });
    }
}
