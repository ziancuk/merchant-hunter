<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Support\Locale;
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
        $middleware->encryptCookies(except: [Locale::COOKIE]);
        $middleware->web(prepend: [SetLocale::class], append: [HandleInertiaRequests::class, SecurityHeaders::class]);
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Translated JSON messages that never echo model class names or framework wording (B-14). The route may not have
        // matched (no web middleware), so the language is resolved from the request here.
        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => $request->expectsJson()
            ? response()->json(['message' => __('errors.not_found', [], Locale::resolve($request))], 404)
            : null);
        $exceptions->render(fn (ThrottleRequestsException $e, Request $request) => $request->expectsJson()
            ? response()->json(['message' => __('errors.too_many_requests', [], Locale::resolve($request))], 429, $e->getHeaders())
            : null);
    })->create();
