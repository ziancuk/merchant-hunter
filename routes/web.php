<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Placeholder until FE-Peta lands (M3.1); BE-Domain adds the real props.
    Route::get('/', fn () => Inertia::render('Map/Index'))->name('map');

    // BE-Domain appends the remaining pages and /api routes below (M2.1).
});
