<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\DTO\ScoreInput;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/** Builds the pure ScoreInput of TRD §7 from prospect attributes (simulated values only count on simulated rows). */
final class ScoreInputFactory
{
    /** @param array<string, mixed> $a prospect attributes (model getAttributes() or an insert array) */
    public static function fromAttributes(array $a, int $categoryWeight, CarbonInterface $now): ScoreInput
    {
        $isSimulatedRow = (bool) ($a['is_simulated'] ?? false);
        $rating = isset($a['rating']) ? (float) $a['rating'] : null;
        $reviews = isset($a['review_count']) ? (int) $a['review_count'] : null;
        $ratingSimulated = (bool) ($a['rating_simulated'] ?? false) || (bool) ($a['reviews_simulated'] ?? false);
        $phoneSimulated = (bool) ($a['phone_simulated'] ?? false);

        $firstSeen = $a['first_seen_at'] ?? null;
        $firstSeen = $firstSeen === null ? $now : Carbon::parse($firstSeen);

        return new ScoreInput(
            categoryWeight: $categoryWeight,
            rating: $rating,
            reviewCount: $reviews,
            ratingCounts: $rating !== null && ($isSimulatedRow || ! $ratingSimulated),
            nearestOutletDistanceM: isset($a['nearest_outlet_distance_m']) ? (int) $a['nearest_outlet_distance_m'] : null,
            neighborOutlets500m: (int) ($a['neighbor_count_500m'] ?? 0),
            areaDensity: (int) ($a['area_density'] ?? 0),
            hasPhone: filled($a['phone'] ?? null) && ($isSimulatedRow || ! $phoneSimulated),
            hasWebsite: filled($a['website'] ?? null),
            hasAddress: filled($a['address'] ?? null),
            isNewOpen: (bool) ($a['is_new_open'] ?? false),
            ageDays: max(0, (int) floor($firstSeen->diffInDays($now, false))),
            isClosed: ($a['lifecycle_status'] ?? 'open') === 'closed',
        );
    }
}
