<?php

declare(strict_types=1);

namespace App\DTO;

use Carbon\CarbonImmutable;

final readonly class BusinessDTO
{
    /**
     * @param  list<string>  $simulatedFields  subset of phone|rating|review_count
     * @param  array<string, mixed>  $rawTags
     */
    public function __construct(
        public string $provider,
        public string $placeId,
        public string $name,
        public string $categoryKey,
        public float $lat,
        public float $lng,
        public ?string $address = null,
        public ?string $phone = null,
        public ?string $website = null,
        public ?float $rating = null,
        public ?int $reviewCount = null,
        public ?string $openingHours = null,
        public bool $isClosedPermanently = false,
        public array $simulatedFields = [],
        public array $rawTags = [],
        public ?CarbonImmutable $fetchedAt = null,
    ) {}

    public function isSimulated(string $field): bool
    {
        return in_array($field, $this->simulatedFields, true);
    }
}
