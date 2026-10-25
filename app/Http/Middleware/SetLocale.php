<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Applies the client's language (X-Locale header or hunter_locale cookie) to the whole request, so every server message is translated. */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale(Locale::resolve($request));

        return $next($request);
    }
}
