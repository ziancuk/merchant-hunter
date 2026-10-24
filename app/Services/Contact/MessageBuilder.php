<?php

declare(strict_types=1);

namespace App\Services\Contact;

final class MessageBuilder
{
    public const PLACEHOLDERS = ['{nama_bisnis}', '{nama_pengirim}', '{kategori}'];

    public function build(string $template, string $businessName, string $senderName, string $categoryName): string
    {
        return strtr($template, [
            '{nama_bisnis}' => $businessName,
            '{nama_pengirim}' => $senderName,
            '{kategori}' => $categoryName,
        ]);
    }

    /** True when the template has at least one known placeholder and no unknown `{…}` token. */
    public static function isValidTemplate(string $template): bool
    {
        preg_match_all('/\{[^{}\s]*\}/', $template, $m);
        $tokens = array_unique($m[0]);
        if ($tokens === []) {
            return false;
        }

        return array_diff($tokens, self::PLACEHOLDERS) === [];
    }
}
