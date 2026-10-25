<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\DataSource;
use Throwable;

/** Masks API keys and tokens in free text that is stored (last_error, scheduler rows) or logged. */
final class Redactor
{
    public const MASK = '[redacted]';

    /**
     * @param  list<string>|null  $secrets  literal secrets to strip (defaults to every stored data source key)
     */
    public static function redact(?string $text, ?array $secrets = null): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }
        foreach ($secrets ?? self::storedSecrets() as $secret) {
            if (strlen($secret) >= 6) {
                $text = str_replace($secret, self::MASK, $text);
            }
        }
        // key=..., api_key=..., token=... query/body parameters.
        $text = preg_replace('/\b((?:api[_-]?)?key|token|access_token|secret|password)=([^&\s"\']+)/i', '$1='.self::MASK, $text) ?? $text;
        // Authorization / API-key headers echoed in error text.
        $text = preg_replace('/\b(authorization|x-goog-api-key)(\s*[:=]\s*)(?:bearer\s+)?[^\s,;"\']+/i', '$1$2'.self::MASK, $text) ?? $text;
        $text = preg_replace('/\bbearer\s+[A-Za-z0-9._\-]{8,}/i', 'Bearer '.self::MASK, $text) ?? $text;

        // Google API keys.
        return preg_replace('/AIza[0-9A-Za-z_\-]{20,}/', self::MASK, $text) ?? $text;
    }

    public static function exception(Throwable $e): string
    {
        return (string) self::redact($e->getMessage());
    }

    /** @return list<string> */
    private static function storedSecrets(): array
    {
        try {
            return DataSource::query()->whereNotNull('api_key')->get()
                ->map(fn (DataSource $s) => (string) $s->api_key)
                ->filter(fn (string $k) => $k !== '')
                ->values()->all();
        } catch (Throwable) {
            return [];
        }
    }
}
