<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [HandleInertiaRequests::class, SecurityHeaders::class]);
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Indonesian JSON messages that never echo model class names or framework wording (B-14).
        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => $request->expectsJson()
            ? response()->json(['message' => 'Data tidak ditemukan.'], 404)
            : null);
        $exceptions->render(fn (ThrottleRequestsException $e, Request $request) => $request->expectsJson()
            ? response()->json(['message' => 'Terlalu banyak permintaan. Coba lagi sebentar lagi.'], 429, $e->getHeaders())
            : null);
    })->create();
