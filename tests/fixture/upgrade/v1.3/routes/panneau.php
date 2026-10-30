<?php

use Illuminate\Support\Facades\Route;
use Panneau\Support\Facade as Panneau;

$namePrefix = config('panneau.routes.name_prefix', config('panneau.routes.prefix') . '.');

Panneau::router()->group(function () use ($namePrefix) {
    Route::middleware(['web'])->group(function () {
        Panneau::router()->auth();
    });

    Route::middleware(['web', 'auth:admin', 'can:view,' . \Panneau\Panneau::class])->group(function () use ($namePrefix) {
        Route::get('/', [\App\Panneau\Http\Controllers\HomeController::class, 'index'])->name($namePrefix . 'home');

        Route::get('orders/export.csv', [\App\Panneau\Http\Controllers\OrdersController::class, 'exportCsv'])->name($namePrefix . 'orders.export');

        Route::match(['post', 'delete'], 'medias/batch/delete', [\App\Panneau\Http\Controllers\OrdersController::class, 'batchDelete']);

        Panneau::router()->resources();
    });
});
