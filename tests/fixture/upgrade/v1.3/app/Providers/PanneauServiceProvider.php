<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Panneau\Support\LocalizedField;
use Panneau\Support\Facade as Panneau;
use Panneau\Fields\Upload;

class PanneauServiceProvider extends ServiceProvider
{
    public function boot()
    {
        LocalizedField::setLocalesResolver(function () {
            return config('locale.locales');
        });

        Panneau::serving(function () {
            Upload::setEndpoint('/admin/upload');
        });
    }
}
