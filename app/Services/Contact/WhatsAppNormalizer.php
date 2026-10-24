<?php

declare(strict_types=1);

namespace App\Services\Contact;

/** Mirrors resources/js/lib/whatsapp.ts: Indonesian mobile numbers to `62…` digits, else null. */
final class WhatsAppNormalizer
{
    /** @param bool $allowSimulated accept the non-routable 0999 demo range (D-62; only when demo.allow_simulated_whatsapp is on) */
    public static function normalize(?string $raw, bool $allowSimulated = false): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $digits = (string) preg_replace('/[^\d+]/', '', $raw);
        if (str_starts_with($digits, '+')) {
            $digits = substr($digits, 1);
        }
        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        if (preg_match('/^628\d{7,11}$/', $digits) === 1) {
            return $digits;
        }

        return $allowSimulated && preg_match('/^62999\d{8}$/', $digits) === 1 ? $digits : null;
    }
}
