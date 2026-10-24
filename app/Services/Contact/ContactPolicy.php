<?php

declare(strict_types=1);

namespace App\Services\Contact;

use App\Enums\ActivityType;
use App\Enums\LifecycleStatus;
use App\Models\Prospect;
use App\Models\ProspectActivity;
use App\Models\User;
use App\Services\Settings\SettingsService;

/** Contact etiquette (D-59, D-62, D-65): opt-out, recent-contact warning, frequency limit, overrides. */
final class ContactPolicy
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly MessageBuilder $messages,
    ) {}

    public function check(Prospect $prospect, User $user): ContactCheck
    {
        $prospect->loadMissing('category');
        $rules = $this->settings->contactRules();

        $normalized = WhatsAppNormalizer::normalize($prospect->phone);
        $simulatedBlocked = $prospect->phone_simulated && ! $this->settings->bool('demo.allow_simulated_whatsapp');

        $blocked = match (true) {
            $prospect->do_not_contact => 'do_not_contact',
            $prospect->lifecycle_status === LifecycleStatus::Closed => 'closed',
            blank($prospect->phone) => 'no_phone',
            $normalized === null, $simulatedBlocked => 'invalid_phone',
            default => null,
        };

        $last = $prospect->last_contacted_at;
        $violation = $last !== null && $last->gt(now()->subDays((int) $rules['min_interval_days']));

        return new ContactCheck(
            blockedReason: $blocked,
            warning: $this->warning($prospect),
            minIntervalViolation: $violation,
            phoneNormalized: $blocked === null ? $normalized : null,
            message: $this->messages->build(
                (string) ($prospect->category?->whatsapp_template ?? ''),
                (string) $prospect->name,
                $user->name,
                (string) ($prospect->category?->name ?? ''),
            ),
            templateCategory: (string) ($prospect->category?->key ?? ''),
            overrideCount: $this->overrideCount($prospect),
            maxOverrides: (int) $rules['max_overrides'],
        );
    }

    /** @return array{type: string, by: string|null, at: string, days_ago: int}|null */
    public function warning(Prospect $prospect): ?array
    {
        $last = $prospect->last_contacted_at;
        if ($last === null) {
            return null;
        }
        $warnDays = $this->settings->int('contact.warn_days');
        if (! $last->gt(now()->subDays($warnDays))) {
            return null;
        }
        $prospect->loadMissing('lastContactedBy:id,name');

        return [
            'type' => 'recent_contact',
            'by' => $prospect->lastContactedBy?->name,
            'at' => $last->copy()->utc()->toIso8601ZuluString(),
            'days_ago' => (int) floor((now()->getTimestamp() - $last->getTimestamp()) / 86400),
        ];
    }

    /** Number of "contacted" activities that used an override. */
    public function overrideCount(Prospect $prospect): int
    {
        return ProspectActivity::query()
            ->where('prospect_id', $prospect->id)
            ->where('type', ActivityType::Contacted->value)
            ->get(['meta'])
            ->filter(fn (ProspectActivity $a) => (bool) ($a->meta['override'] ?? false))
            ->count();
    }
}
