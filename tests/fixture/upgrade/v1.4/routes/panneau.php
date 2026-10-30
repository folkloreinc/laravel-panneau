<?php

use Illuminate\Support\Facades\Route;
use Panneau\Support\Facade as Panneau;
use App\Panneau\Http\Controllers\HomeController;
use App\Panneau\Http\Controllers\AccountController;
use App\Panneau\Http\Controllers\UploadController;
use App\Panneau\Http\Controllers\PagesController;

Panneau::router()->group(function () {
    Route::any('tus/{any?}', function () {
        return app('tus-server')->serve();
    })->where('any', '.*')->name('upload.tus');

    Route::middleware(['web'])->group(function () {
        Panneau::router()->auth();
    });

    Route::middleware(['web', 'auth', 'can:view,' . \Panneau\Panneau::class])->group(function () {
        Route::get('/', [HomeController::class, 'index'])->name('home');

        Route::post('upload', [UploadController::class, 'upload'])->name('upload');

        Route::get('account', [AccountController::class, 'index'])->name('account');

        Route::post('account', [AccountController::class, 'update'])->name('account.update');

        Route::get('/pages/sections', [PagesController::class, 'sections'])->name('pages.sections');

        Panneau::router()->resources();
    });
});
