<?php

use App\Http\Controllers\Api\DigestController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\MapController;
use App\Http\Controllers\Api\MasterCategoryController;
use App\Http\Controllers\Api\MasterDataSourceController;
use App\Http\Controllers\Api\MasterOutletController;
use App\Http\Controllers\Api\PipelineController;
use App\Http\Controllers\Api\ProspectController;
use App\Http\Controllers\Api\RouteController;
use App\Http\Controllers\Api\SchedulerController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Pages\PagesController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Inertia pages (CONTRACTS §1)
    Route::get('/', [PagesController::class, 'map'])->name('map');
    Route::get('/pipeline', [PagesController::class, 'pipeline'])->name('pipeline');
    Route::get('/dashboard', [PagesController::class, 'dashboard'])->name('dashboard');
    Route::get('/impact', [PagesController::class, 'impact'])->name('impact');
    Route::get('/master/data-sources', [PagesController::class, 'dataSources'])->name('master.data-sources');
    Route::get('/master/outlets', [PagesController::class, 'outlets'])->name('master.outlets');
    Route::get('/master/outlets/review', [PagesController::class, 'outletReview'])->name('master.outlets.review');
    Route::get('/master/categories', [PagesController::class, 'categories'])->name('master.categories');
    Route::get('/scheduler', [PagesController::class, 'scheduler'])->name('scheduler');
    Route::get('/master/settings', [PagesController::class, 'settings'])->name('master.settings');

    // CSV exports
    Route::get('/prospects/export.csv', [ExportController::class, 'prospects'])->middleware('throttle:10,1,export');
    Route::get('/dashboard/export.csv', [ExportController::class, 'dashboard'])->middleware('throttle:10,1,export');

    // JSON API (session + CSRF, not tokens)
    Route::prefix('api')->group(function () {
        // Map: 300/min
        Route::middleware('throttle:300,1,map')->group(function () {
            Route::get('/map/outlets', [MapController::class, 'outlets']);
            Route::get('/map/prospects', [MapController::class, 'prospects']);
            Route::get('/map/whitespace', [MapController::class, 'whitespace']);
            Route::get('/search', [MapController::class, 'search']);
        });

        Route::get('/prospects/{prospect}', [ProspectController::class, 'show']);
        Route::post('/prospects/bulk', [ProspectController::class, 'bulk']);
        Route::post('/prospects/{prospect}/contact-check', [ProspectController::class, 'contactCheck']);
        Route::post('/prospects/{prospect}/contact', [ProspectController::class, 'contact']);
        Route::post('/prospects/{prospect}/status', [ProspectController::class, 'status']);
        Route::post('/prospects/{prospect}/notes', [ProspectController::class, 'note']);
        Route::post('/prospects/{prospect}/opt-out', [ProspectController::class, 'optOut']);
        Route::delete('/prospects/{prospect}/opt-out', [ProspectController::class, 'optIn']);
        Route::post('/prospects/{prospect}/message-ai', [ProspectController::class, 'messageAi'])->middleware('throttle:10,1,ai');

        Route::get('/pipeline/cards', [PipelineController::class, 'cards']);
        Route::get('/pipeline/table', [PipelineController::class, 'table']);
        Route::post('/routes/plan', [RouteController::class, 'plan']);
        Route::get('/digest', [DigestController::class, 'show']);

        Route::get('/settings', [SettingsController::class, 'show']);
        Route::put('/settings', [SettingsController::class, 'update']);
        Route::put('/settings/impact', [SettingsController::class, 'impact']);

        Route::get('/master/data-sources', [MasterDataSourceController::class, 'index']);
        Route::put('/master/data-sources/{dataSource}', [MasterDataSourceController::class, 'update']);
        Route::post('/master/data-sources/{dataSource}/test', [MasterDataSourceController::class, 'test'])->middleware('throttle:6,1,conn');

        Route::get('/master/outlets', [MasterOutletController::class, 'index']);
        Route::post('/master/outlets', [MasterOutletController::class, 'store']);
        Route::post('/master/outlets/import', [MasterOutletController::class, 'import'])->middleware('throttle:10,1,import');
        Route::get('/master/outlets/review', [MasterOutletController::class, 'review']);
        Route::put('/master/outlets/{outlet}', [MasterOutletController::class, 'update']);
        Route::delete('/master/outlets/{outlet}', [MasterOutletController::class, 'destroy']);
        Route::post('/master/outlets/{outlet}/retry-geocode', [MasterOutletController::class, 'retryGeocode']);
        Route::post('/master/outlets/{outlet}/resolve', [MasterOutletController::class, 'resolve']);

        Route::get('/master/categories', [MasterCategoryController::class, 'index']);
        Route::post('/master/categories', [MasterCategoryController::class, 'store']);
        Route::put('/master/categories/{category}', [MasterCategoryController::class, 'update']);

        Route::get('/scheduler/runs', [SchedulerController::class, 'index']);
        Route::get('/scheduler/runs/{run}', [SchedulerController::class, 'show']);
    });
});
