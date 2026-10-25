<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security response headers (D-83, D-124).
 *
 * The Content-Security-Policy is built per request with a script nonce (the inline theme bootstrap in app.blade.php and
 * the Vite tags carry it). `hunter.csp` selects `enforce` (default), `report-only` or `off`.
 */
class SecurityHeaders
{
    /** Remote basemap host (style JSON, vector tiles, sprites and glyphs all come from here). */
    public const BASEMAP_HOST = 'https://tiles.openfreemap.org';

    public const PERMISSIONS_POLICY = 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), serial=(), bluetooth=(), accelerometer=(), gyroscope=(), magnetometer=(), interest-cohort=()';

    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));
        // Must happen before the view renders: adds the nonce to the @vite tags and feeds Vite::cspNonce().
        Vite::useCspNonce($nonce);

        $response = $next($request);
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', self::PERMISSIONS_POLICY);

        $mode = (string) config('hunter.csp', 'enforce');
        if ($mode === 'enforce' || $mode === 'report-only') {
            $name = $mode === 'enforce' ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only';
            $response->headers->set($name, $this->policy($nonce));
        }

        // HSTS only makes sense (and is only honoured) over HTTPS.
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    public function policy(string $nonce): string
    {
        $dev = $this->viteDevOrigin();
        $devWs = $dev !== null ? preg_replace('#^http#', 'ws', $dev) : null;

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'nonce-{$nonce}'", ...($dev !== null ? [$dev] : [])],
            // Vue/Tailwind bind inline style attributes (positioning, colours); styles cannot run code.
            'style-src' => ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com', ...($dev !== null ? [$dev] : [])],
            'font-src' => ["'self'", 'data:', 'https://fonts.gstatic.com', ...($dev !== null ? [$dev] : [])],
            'img-src' => ["'self'", 'data:', 'blob:', self::BASEMAP_HOST],
            'connect-src' => ["'self'", self::BASEMAP_HOST, ...($dev !== null ? [$dev, (string) $devWs] : [])],
            // MapLibre runs its tile workers from a blob: URL.
            'worker-src' => ["'self'", 'blob:'],
            'child-src' => ["'self'", 'blob:'],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'self'"],
        ];

        return implode('; ', array_map(fn (string $d, array $v) => $d.' '.implode(' ', $v), array_keys($directives), $directives));
    }

    /** Origin of the Vite dev server when `npm run dev` is running (public/hot), so local development keeps working. */
    private function viteDevOrigin(): ?string
    {
        if (! Vite::isRunningHot()) {
            return null;
        }
        $url = trim((string) @file_get_contents(Vite::hotFile()));
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }
        $host = str_contains($parts['host'], ':') ? '['.trim($parts['host'], '[]').']' : $parts['host'];

        return $parts['scheme'].'://'.$host.(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
