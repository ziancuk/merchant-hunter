<?php

declare(strict_types=1);

namespace App\Services\Providers;

use App\DTO\BusinessDTO;
use App\DTO\ConnectionStatus;
use App\DTO\OutletPoint;
use App\DTO\ProviderResult;
use App\Models\Category;
use App\Models\DataSource;
use App\Services\Providers\Concerns\UsesDataSource;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Places API (New) `places:searchNearby` (D-47, D-66).
 *
 * One call per category (its google_types group), at most 20 results per call, no pagination. Only the
 * place_id is permanent; every other field is cache that expires 30 days after fetch (SyncService stamps
 * prospects.expires_at, hunter:purge-expired clears the content). Calls are counted and refused beyond
 * monthly_quota. Inactive by default: the owner must verify Google's ToS before enabling it.
 */
class GoogleProvider implements DataProvider
{
    use UsesDataSource;

    public const ENDPOINT = 'https://places.googleapis.com/v1/places:searchNearby';

    public const MAX_RESULTS = 20;

    public const CACHE_DAYS = 30;

    private const FIELD_MASK = 'places.id,places.displayName,places.location,places.formattedAddress,places.nationalPhoneNumber,places.websiteUri,places.rating,places.userRatingCount,places.regularOpeningHours.weekdayDescriptions,places.businessStatus';

    public function key(): string
    {
        return 'google';
    }

    public function isAvailable(): bool
    {
        $s = $this->source();

        return $s !== null && $s->is_active && $s->hasKey();
    }

    public function searchNearby(OutletPoint $center, int $radiusMeters, array $categories): ProviderResult
    {
        $source = $this->requireSource();
        $groups = array_values(array_filter($categories, fn (Category $c) => ! empty($c->google_types)));
        $needed = count($groups);
        if ($source->monthly_quota !== null && $source->calls_this_month + $needed > $source->monthly_quota) {
            throw new QuotaExceededException("Google monthly quota ({$source->monthly_quota}) would be exceeded; refusing {$needed} call(s)");
        }

        $now = CarbonImmutable::now();
        $byPlace = [];
        $calls = 0;
        foreach ($groups as $category) {
            $this->guardQuota();
            $body = [
                'includedTypes' => array_values($category->google_types),
                'maxResultCount' => self::MAX_RESULTS,
                'locationRestriction' => ['circle' => [
                    'center' => ['latitude' => $center->lat, 'longitude' => $center->lng],
                    'radius' => (float) min(50000, $radiusMeters),
                ]],
            ];
            $json = $this->call($body);
            $calls++;
            foreach ($json['places'] ?? [] as $place) {
                $dto = $this->map($place, $category->key, $now);
                if ($dto !== null && ! isset($byPlace[$dto->placeId])) {
                    $byPlace[$dto->placeId] = $dto;
                }
            }
        }

        return new ProviderResult(array_values($byPlace), false, true, $calls);
    }

    public function testConnection(): ConnectionStatus
    {
        if (! $this->isAvailable()) {
            return new ConnectionStatus(false, 'Google provider is inactive or has no API key');
        }
        $start = microtime(true);
        try {
            $this->guardQuota();
            $this->call([
                'includedTypes' => ['restaurant'],
                'maxResultCount' => 1,
                'locationRestriction' => ['circle' => ['center' => ['latitude' => -6.2, 'longitude' => 106.8], 'radius' => 500.0]],
            ]);
        } catch (ProviderException $e) {
            // The detail (Google's own error text, transport errors) goes to the log only; the client gets a generic message.
            Log::warning('Google connection test failed', ['reason' => $this->redact($e->getMessage()), 'status' => $e->status, 'retryable' => $e->retryable]);
            $message = $e instanceof QuotaExceededException
                ? 'Kuota bulanan Google sudah habis.'
                : 'Koneksi ke Google gagal. Periksa API key dan kuota; detail ada di log server.';

            return new ConnectionStatus(false, $message, (int) round((microtime(true) - $start) * 1000));
        }

        return new ConnectionStatus(true, 'Google Places API reachable', (int) round((microtime(true) - $start) * 1000));
    }

    /** Strips the API key from text destined for logs (defence in depth; the key only travels in a header). */
    private function redact(string $text): string
    {
        $key = (string) $this->source()?->api_key;

        return $key === '' ? $text : str_replace($key, '[redacted]', $text);
    }

    private function requireSource(): DataSource
    {
        $this->refreshSource();
        $s = $this->source();
        if ($s === null || ! $s->is_active || ! $s->hasKey()) {
            throw new ProviderException('Google provider is inactive or has no API key');
        }

        return $s;
    }

    /** Refuse the next call when the monthly quota is used up (re-read: other jobs may have consumed it). */
    private function guardQuota(): void
    {
        $this->refreshSource();
        $s = $this->source();
        if ($s !== null && $s->monthly_quota !== null && $s->calls_this_month >= $s->monthly_quota) {
            throw new QuotaExceededException("Google monthly quota ({$s->monthly_quota}) used up");
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function call(array $body): array
    {
        $source = $this->requireSource();
        try {
            $response = Http::withHeaders([
                'X-Goog-Api-Key' => (string) $source->api_key,
                'X-Goog-FieldMask' => self::FIELD_MASK,
            ])->timeout(15)->acceptJson()->asJson()->post(self::ENDPOINT, $body);
        } catch (ConnectionException $e) {
            throw new ProviderException('Google connection failed: '.$e->getMessage(), true);
        }
        // Every request that reached Google is billable/quota-relevant.
        $source->increment('calls_this_month');
        $this->refreshSource();

        return $this->decode($response);
    }

    /** @return array<string, mixed> */
    private function decode(Response $response): array
    {
        if ($response->status() === 429 || ($response->json('error.status') === 'RESOURCE_EXHAUSTED')) {
            throw new QuotaExceededException('Google reported quota exhausted (HTTP '.$response->status().')');
        }
        if (! $response->successful()) {
            $msg = (string) ($response->json('error.message') ?? 'HTTP '.$response->status());

            throw new ProviderException('Google error: '.mb_substr($msg, 0, 200), $response->serverError(), $response->status());
        }
        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /** @param array<string, mixed> $place */
    private function map(array $place, string $categoryKey, CarbonImmutable $now): ?BusinessDTO
    {
        $id = $place['id'] ?? null;
        $lat = $place['location']['latitude'] ?? null;
        $lng = $place['location']['longitude'] ?? null;
        $name = $place['displayName']['text'] ?? null;
        if (! is_string($id) || $id === '' || ! is_numeric($lat) || ! is_numeric($lng) || ! is_string($name) || $name === '') {
            return null;
        }
        $hours = $place['regularOpeningHours']['weekdayDescriptions'] ?? null;

        return new BusinessDTO(
            provider: 'google',
            placeId: $id,
            name: mb_substr($name, 0, 200),
            categoryKey: $categoryKey,
            lat: (float) $lat,
            lng: (float) $lng,
            address: isset($place['formattedAddress']) ? mb_substr((string) $place['formattedAddress'], 0, 300) : null,
            phone: isset($place['nationalPhoneNumber']) ? mb_substr((string) $place['nationalPhoneNumber'], 0, 40) : null,
            website: isset($place['websiteUri']) ? mb_substr((string) $place['websiteUri'], 0, 255) : null,
            rating: isset($place['rating']) ? round((float) $place['rating'], 1) : null,
            reviewCount: isset($place['userRatingCount']) ? (int) $place['userRatingCount'] : null,
            openingHours: is_array($hours) ? mb_substr(implode('; ', $hours), 0, 255) : null,
            isClosedPermanently: ($place['businessStatus'] ?? null) === 'CLOSED_PERMANENTLY',
            simulatedFields: [],
            rawTags: [],
            fetchedAt: $now,
        );
    }
}
