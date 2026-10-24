<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\LifecycleStatus;
use App\Models\Prospect;
use Carbon\CarbonInterface;

/** Small helpers shared by resources. */
final class Support
{
    /** ISO-8601 UTC with a Z suffix (CONTRACTS: "2026-10-24T01:00:00Z"). */
    public static function iso(?CarbonInterface $date): ?string
    {
        return $date?->copy()->utc()->toIso8601ZuluString();
    }

    /**
     * Only http/https links are exposed; javascript:, data: and friends are dropped (TRD §9).
     */
    public static function safeUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }
        $url = trim($url);
        if (preg_match('/[\x00-\x20\x7f]/', $url) === 1) {
            return null;
        }
        $scheme = parse_url($url, PHP_URL_SCHEME);

        return is_string($scheme) && in_array(strtolower($scheme), ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) !== false
            ? $url
            : null;
    }

    /** @return list<string> subset of new_open, simulated, closed, opt_out */
    public static function badges(Prospect $p): array
    {
        $badges = [];
        if ($p->is_new_open) {
            $badges[] = 'new_open';
        }
        if ($p->is_simulated || $p->phone_simulated || $p->rating_simulated || $p->reviews_simulated) {
            $badges[] = 'simulated';
        }
        if ($p->lifecycle_status === LifecycleStatus::Closed) {
            $badges[] = 'closed';
        }
        if ($p->do_not_contact) {
            $badges[] = 'opt_out';
        }

        return $badges;
    }
}
