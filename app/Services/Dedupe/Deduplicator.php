<?php

declare(strict_types=1);

namespace App\Services\Dedupe;

use App\DTO\BusinessDTO;
use App\Models\Outlet;
use App\Services\Geo\Haversine;

/** Dedupe rules of TRD §5: exclude Hitachi merchants and merge cross-provider duplicates. */
final class Deduplicator
{
    public const OUTLET_RADIUS_M = 100;

    public const OUTLET_SIMILARITY = 0.80;

    public const OUTLET_NEAR_M = 30;

    public const OUTLET_NEAR_SIMILARITY = 0.6;

    public const CROSS_PROVIDER_RADIUS_M = 40;

    public const CROSS_PROVIDER_SIMILARITY = 0.85;

    public function __construct(private readonly OutletIndex $outlets) {}

    /** @param iterable<Outlet> $outlets */
    public static function forOutlets(iterable $outlets): self
    {
        return new self(new OutletIndex($outlets));
    }

    /** True when a business at this distance/similarity is the same shop as an outlet (TRD §5 point 2). */
    public static function isSameMerchant(float $distanceM, float $similarity): bool
    {
        if ($distanceM <= self::OUTLET_NEAR_M && $similarity >= self::OUTLET_NEAR_SIMILARITY) {
            return true;
        }

        return $distanceM <= self::OUTLET_RADIUS_M && $similarity >= self::OUTLET_SIMILARITY;
    }

    /**
     * The Hitachi outlet this business actually is, if any.
     *
     * @return array{outlet: array{id: int, code: string, name: string, norm: string, lat: float, lng: float, city: string, province_id: ?int, active: bool}, distance: float, similarity: float}|null
     */
    public function matchingOutlet(string $name, float $lat, float $lng): ?array
    {
        $norm = NameNormalizer::normalize($name);
        foreach ($this->outlets->within($lat, $lng, self::OUTLET_RADIUS_M) as $hit) {
            $similarity = Similarity::normalized($norm, $hit['outlet']['norm']);
            if (self::isSameMerchant($hit['distance'], $similarity)) {
                return $hit + ['similarity' => $similarity];
            }
        }

        return null;
    }

    /**
     * Split businesses into prospects and Hitachi merchants.
     *
     * @param  list<BusinessDTO>  $businesses
     * @return array{kept: list<BusinessDTO>, excluded: list<BusinessDTO>}
     */
    public function filter(array $businesses): array
    {
        $kept = [];
        $excluded = [];
        foreach ($businesses as $b) {
            if ($this->matchingOutlet($b->name, $b->lat, $b->lng) !== null) {
                $excluded[] = $b;
            } else {
                $kept[] = $b;
            }
        }

        return ['kept' => $kept, 'excluded' => $excluded];
    }

    /**
     * Cross-provider merge (TRD §5 point 5). Lists are ordered by provider priority (highest first); a
     * business from a lower-priority list within 40 m and similarity >= 0.85 of an already-kept one is
     * merged into it: empty fields are completed and the provider is recorded in rawTags.merged_from.
     *
     * @param  list<list<BusinessDTO>>  $lists
     * @return list<BusinessDTO>
     */
    public function mergeAcrossProviders(array $lists): array
    {
        /** @var list<BusinessDTO> $kept */
        $kept = [];
        $mergedFrom = [];
        foreach ($lists as $list) {
            foreach ($list as $b) {
                $matchIndex = null;
                foreach ($kept as $i => $k) {
                    if ($k->provider === $b->provider) {
                        continue;
                    }
                    if (Haversine::distance($k->lat, $k->lng, $b->lat, $b->lng) <= self::CROSS_PROVIDER_RADIUS_M
                        && Similarity::names($k->name, $b->name) >= self::CROSS_PROVIDER_SIMILARITY) {
                        $matchIndex = $i;
                        break;
                    }
                }
                if ($matchIndex === null) {
                    $kept[] = $b;

                    continue;
                }
                $mergedFrom[$matchIndex][] = $b->provider.':'.$b->placeId;
                $kept[$matchIndex] = $this->complete($kept[$matchIndex], $b, $mergedFrom[$matchIndex]);
            }
        }

        return $kept;
    }

    /**
     * @param  list<string>  $mergedFrom
     */
    private function complete(BusinessDTO $primary, BusinessDTO $other, array $mergedFrom): BusinessDTO
    {
        $tags = $primary->rawTags;
        $tags['merged_from'] = array_values(array_unique($mergedFrom));

        return new BusinessDTO(
            provider: $primary->provider,
            placeId: $primary->placeId,
            name: $primary->name,
            categoryKey: $primary->categoryKey,
            lat: $primary->lat,
            lng: $primary->lng,
            address: $primary->address ?? $other->address,
            phone: $primary->phone ?? $other->phone,
            website: $primary->website ?? $other->website,
            rating: $primary->rating ?? $other->rating,
            reviewCount: $primary->reviewCount ?? $other->reviewCount,
            openingHours: $primary->openingHours ?? $other->openingHours,
            isClosedPermanently: $primary->isClosedPermanently,
            simulatedFields: $primary->simulatedFields,
            rawTags: $tags,
            fetchedAt: $primary->fetchedAt,
        );
    }
}
