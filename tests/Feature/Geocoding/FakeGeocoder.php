<?php

declare(strict_types=1);

namespace Tests\Feature\Geocoding;

use App\Services\Geocoding\DetailedReverseGeocoder;
use App\Services\Geocoding\GeocoderInterface;
use Throwable;

class FakeGeocoder implements DetailedReverseGeocoder, GeocoderInterface
{
    /** @var list<array{float, float}> */
    public array $reverseCalls = [];

    /** @var list<array{string, string, ?float, ?float}> */
    public array $forwardCalls = [];

    /** @var array{address: string, city: ?string}|Throwable|null */
    public mixed $reverseAnswer = ['address' => 'Jl. Hasil 1, Jakarta', 'city' => 'Jakarta Pusat'];

    /** @var list<array{display_name: string, lat: float, lng: float, score: int}>|Throwable */
    public mixed $forwardAnswer = [];

    public static function install(): self
    {
        $g = new self;
        app()->instance(GeocoderInterface::class, $g);

        return $g;
    }

    public function reverse(float $lat, float $lng): ?string
    {
        return $this->reverseDetailed($lat, $lng)['address'] ?? null;
    }

    public function reverseDetailed(float $lat, float $lng): ?array
    {
        $this->reverseCalls[] = [$lat, $lng];
        if ($this->reverseAnswer instanceof Throwable) {
            throw $this->reverseAnswer;
        }

        return $this->reverseAnswer;
    }

    /** @var array{display_name: string, lat: float, lng: float}|Throwable|null */
    public mixed $streetAnswer = null;

    /** @var list<array{string, string}> */
    public array $streetCalls = [];

    /** @var array<string, array{display_name: string, lat: float, lng: float}|null> by kind */
    public array $placeAnswers = [];

    /** @var list<array{string, string, string}> */
    public array $placeCalls = [];

    public function forwardPlace(string $text, string $city, string $kind): ?array
    {
        $this->placeCalls[] = [$text, $city, $kind];

        return $this->placeAnswers[$kind] ?? null;
    }

    public function forwardStreet(string $street, string $city): ?array
    {
        $this->streetCalls[] = [$street, $city];
        if ($this->streetAnswer instanceof Throwable) {
            throw $this->streetAnswer;
        }

        return $this->streetAnswer;
    }

    public function forward(string $name, string $city, ?float $nearLat = null, ?float $nearLng = null): array
    {
        $this->forwardCalls[] = [$name, $city, $nearLat, $nearLng];
        if ($this->forwardAnswer instanceof Throwable) {
            throw $this->forwardAnswer;
        }

        return $this->forwardAnswer;
    }
}
