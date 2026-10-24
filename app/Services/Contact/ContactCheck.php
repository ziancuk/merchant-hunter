<?php

declare(strict_types=1);

namespace App\Services\Contact;

final readonly class ContactCheck
{
    /**
     * @param  array{type: string, by: string|null, at: string, days_ago: int}|null  $warning
     */
    public function __construct(
        public ?string $blockedReason,
        public ?array $warning,
        public bool $minIntervalViolation,
        public ?string $phoneNormalized,
        public string $message,
        public string $templateCategory,
        public int $overrideCount,
        public int $maxOverrides,
    ) {}

    public function allowed(): bool
    {
        return $this->blockedReason === null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed(),
            'blocked_reason' => $this->blockedReason,
            'warning' => $this->warning,
            'min_interval_violation' => $this->minIntervalViolation,
            'phone_normalized' => $this->phoneNormalized,
            'message' => $this->message,
            'template_category' => $this->templateCategory,
            'override_count' => $this->overrideCount,
            'max_overrides' => $this->maxOverrides,
        ];
    }
}
