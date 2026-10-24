<?php

declare(strict_types=1);

namespace App\Services\Contact;

/** Mirrors resources/js/lib/whatsapp.ts: Indonesian mobile numbers to `62…` digits, else null. */
final class WhatsAppNormalizer
{
    public static function normalize(?string $raw): ?string
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

        return preg_match('/^628\d{7,11}$/', $digits) === 1 ? $digits : null;
    }
}
