<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => ['user' => $user ? $user->only('id', 'name', 'email') : null],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'app' => [
                'name' => config('app.name'),
                'basemap' => config('hunter.basemap') === 'local' ? 'local' : 'openfreemap',
                'ai_enabled' => filled(config('hunter.ai.key')),
                // Server-decided switch for ?intro/?motion overrides and window.__hunterMap (D-93, D-119); the client never sees APP_ENV.
                'debug_hooks' => ! app()->isProduction(),
            ],
        ];
    }
}
