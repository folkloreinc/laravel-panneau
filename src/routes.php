<?php

use Illuminate\Support\Facades\Route;

Route::prefix('panneau')
    ->middleware(\Panneau\Http\Middleware\DispatchHandlingRequestEvent::class)
    ->namespace('\Panneau\Http\Controllers')
    ->group(function () {
        Panneau::routes();
    });
