<?php

declare(strict_types=1);

namespace App\Services\Providers;

use App\DTO\BusinessDTO;
use App\DTO\ConnectionStatus;
use App\DTO\OutletPoint;
use App\DTO\ProviderResult;
use App\DTO\UsageInfo;
use App\Services\Geo\Haversine;
use Carbon\CarbonImmutable;

/**
 * Deterministic simulated businesses (TRD §4). No I/O. Phone numbers use the non-routable 0999 range (D-62)
 * and every invented field is listed in simulatedFields.
 */
class MockProvider implements DataProvider
{
    public const MIN_PER_OUTLET = 55;

    public const MAX_PER_OUTLET = 95;

    private const ADJECTIVES = [
        'Sinar', 'Berkah', 'Maju', 'Jaya', 'Mutiara', 'Harapan', 'Cahaya', 'Sumber', 'Rejeki', 'Karya',
        'Indah', 'Sentosa', 'Makmur', 'Lestari', 'Bahagia', 'Mekar', 'Sejahtera', 'Gemilang', 'Prima', 'Utama',
        'Bintang', 'Surya', 'Pelangi', 'Nusa', 'Bumi', 'Kencana', 'Mulia', 'Abadi', 'Setia', 'Anugrah',
    ];

    private const PLACES = [
        'Senja', 'Pagi', 'Kita', 'Raya', 'Timur', 'Barat', 'Selatan', 'Utara', 'Baru', 'Asri',
        'Permata', 'Citra', 'Sari', 'Wangi', 'Ceria', 'Damai', 'Lima', 'Tiga', 'Mandiri', 'Bersama',
    ];

    /** @var array<string, list<string>> */
    private const TEMPLATES = [
        'restaurant' => ['Rumah Makan {a}', 'Resto {a} {p}', 'Warung {a}', 'Dapur {a} {p}'],
        'cafe' => ['Kopi {a}', 'Kafe {a} {p}', 'Kedai Kopi {p}', 'Ngopi {a}'],
        'fast_food' => ['Ayam Goreng {a}', 'Burger {a}', 'Es Krim {a} {p}', 'Mie Instan {p}'],
        'bakery' => ['Roti {a}', 'Bakery {a} {p}', 'Toko Roti {p}'],
        'minimarket' => ['Minimarket {a}', 'Toko {a} {p}', 'Kios {a}', 'Warung {a} {p}'],
        'fashion' => ['Butik {a}', 'Toko Busana {a}', 'Fashion {a} {p}', 'Sepatu {a}'],
        'pharmacy' => ['Apotek {a}', 'Apotek {a} {p}', 'Farma {a}'],
        'beauty' => ['Salon {a}', 'Kecantikan {a} {p}', 'Barbershop {a}'],
        'laundry' => ['Laundry {a}', 'Laundry {a} {p}', 'Binatu {p}'],
        'electronics' => ['Toko Elektronik {a}', 'Ponsel {a} {p}', 'Komputer {a}'],
        'automotive' => ['Bengkel {a}', 'Bengkel {a} {p}', 'Service Motor {p}'],
        'lodging' => ['Hotel {a}', 'Penginapan {a} {p}', 'Guest House {p}'],
    ];

    private const STREETS = ['Merdeka', 'Melati', 'Mawar', 'Kenanga', 'Anggrek', 'Sudirman', 'Diponegoro', 'Pahlawan', 'Veteran', 'Kemuning', 'Flamboyan', 'Cendana'];

    public function __construct(private readonly int $seed = 42) {}

    public function key(): string
    {
        return 'mock';
    }

    /** Always usable: no network and no key. Whether it takes part in a sync is decided by ProviderRegistry. */
    public function isAvailable(): bool
    {
        return true;
    }

    public function withSeed(int $seed): static
    {
        return new static($seed);
    }

    public function searchNearby(OutletPoint $center, int $radiusMeters, array $categories): ProviderResult
    {
        if ($categories === []) {
            return new ProviderResult([], false, true, 0);
        }
        $rng = SeededRandom::forKey($center->code, $this->seed);
        $count = $rng->int(self::MIN_PER_OUTLET, self::MAX_PER_OUTLET);
        $weights = array_map(fn ($c) => max(1, (int) $c->weight), $categories);
        $now = CarbonImmutable::now();
        $maxRadius = max(100, $radiusMeters - 40);
        $businesses = [];

        for ($i = 1; $i <= $count; $i++) {
            $category = $rng->weighted($categories, $weights);
            // An exponent near 0.5 spreads points about uniformly over the disc (radial spread).
            $distance = $maxRadius * (($rng->float()) ** 0.55);
            $bearing = $rng->float() * 2 * M_PI;
            $lat = $center->lat + rad2deg($distance * cos($bearing) / Haversine::EARTH_RADIUS_M);
            $lng = $center->lng + rad2deg($distance * sin($bearing) / (Haversine::EARTH_RADIUS_M * max(0.01, cos(deg2rad($center->lat)))));
            $name = $this->name($rng, $category->key);
            $businesses[] = $this->build($rng, sprintf('mock/%s/%d/%03d', $center->code, $this->seed, $i), $name, $category->key, round($lat, 6), round($lng, 6), $now);
        }

        return new ProviderResult($businesses, false, true, 0);
    }

    /**
     * Fill empty phone / rating / review_count with simulated values (online enrichment, TRD §4).
     * Deterministic per place id; filled fields are listed in simulatedFields.
     */
    public function enrich(BusinessDTO $business): BusinessDTO
    {
        $rng = SeededRandom::forKey($business->placeId, $this->seed);
        $simulated = $business->simulatedFields;
        $phone = $business->phone;
        $rating = $business->rating;
        $reviews = $business->reviewCount;

        if ($phone === null) {
            $phone = $this->phone($rng);
            $simulated[] = 'phone';
        }
        if ($rating === null) {
            $rating = $this->rating($rng);
            $simulated[] = 'rating';
        }
        if ($reviews === null) {
            $reviews = $this->reviews($rng);
            $simulated[] = 'review_count';
        }

        return new BusinessDTO(
            provider: $business->provider,
            placeId: $business->placeId,
            name: $business->name,
            categoryKey: $business->categoryKey,
            lat: $business->lat,
            lng: $business->lng,
            address: $business->address,
            phone: $phone,
            website: $business->website,
            rating: $rating,
            reviewCount: $reviews,
            openingHours: $business->openingHours,
            isClosedPermanently: $business->isClosedPermanently,
            simulatedFields: array_values(array_unique($simulated)),
            rawTags: $business->rawTags,
            fetchedAt: $business->fetchedAt,
        );
    }

    public function testConnection(): ConnectionStatus
    {
        return new ConnectionStatus(true, 'Simulated provider: no external connection needed', 0);
    }

    public function usage(): UsageInfo
    {
        return new UsageInfo(0, null, 0.0);
    }

    private function build(SeededRandom $rng, string $placeId, string $name, string $categoryKey, float $lat, float $lng, CarbonImmutable $now): BusinessDTO
    {
        $simulated = [];
        $phone = null;
        $rating = null;
        $reviews = null;
        if ($rng->chance(0.82)) {
            $phone = $this->phone($rng);
            $simulated[] = 'phone';
        }
        if ($rng->chance(0.88)) {
            $rating = $this->rating($rng);
            $reviews = $this->reviews($rng);
            $simulated[] = 'rating';
            $simulated[] = 'review_count';
        }
        $address = $rng->chance(0.6)
            ? sprintf('Jl. %s No. %d', $rng->pick(self::STREETS), $rng->int(1, 120))
            : null;

        return new BusinessDTO(
            provider: 'mock',
            placeId: $placeId,
            name: $name,
            categoryKey: $categoryKey,
            lat: $lat,
            lng: $lng,
            address: $address,
            phone: $phone,
            website: null,
            rating: $rating,
            reviewCount: $reviews,
            openingHours: $rng->chance(0.5) ? '08:00-21:00' : null,
            isClosedPermanently: false,
            simulatedFields: $simulated,
            rawTags: ['simulated' => 'true'],
            fetchedAt: $now,
        );
    }

    private function name(SeededRandom $rng, string $categoryKey): string
    {
        $template = $rng->pick(self::TEMPLATES[$categoryKey] ?? ['Usaha {a} {p}']);

        return str_replace(['{a}', '{p}'], [$rng->pick(self::ADJECTIVES), $rng->pick(self::PLACES)], $template);
    }

    /** Non-routable 0999 range: never a real operator number. */
    private function phone(SeededRandom $rng): string
    {
        return sprintf('0999-%04d-%04d', $rng->int(0, 9999), $rng->int(0, 9999));
    }

    private function rating(SeededRandom $rng): float
    {
        // Skewed towards 4.0-4.6 like real review data.
        $r = 3.0 + 2.0 * (($rng->float() + $rng->float() + $rng->float()) / 3);

        return round(min(5.0, max(3.0, $r)), 1);
    }

    private function reviews(SeededRandom $rng): int
    {
        return (int) round(exp($rng->float() * log(900)));
    }
}
