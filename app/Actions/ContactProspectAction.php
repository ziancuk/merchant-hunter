<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ActivityType;
use App\Enums\PipelineStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Prospect;
use App\Models\ProspectActivity;
use App\Models\User;
use App\Services\Contact\ContactPolicy;
use Illuminate\Support\Facades\DB;

/** Records a WhatsApp contact (D-65) after enforcing opt-out, frequency limit and override rules. */
final class ContactProspectAction
{
    public function __construct(private readonly ContactPolicy $policy) {}

    public function handle(Prospect $prospect, User $user, string $message, ?string $overrideReason = null): Prospect
    {
        return DB::transaction(function () use ($prospect, $user, $message, $overrideReason) {
            // Re-read under a row lock so two concurrent contacts cannot both pass the interval/override checks
            // nor lose a contact_count increment (no-op on SQLite, effective on MySQL/PostgreSQL).
            $prospect = Prospect::query()->lockForUpdate()->findOrFail($prospect->id);
            $check = $this->policy->check($prospect, $user);

            if ($check->blockedReason !== null) {
                throw new BusinessRuleException(__('api.contact.'.$check->blockedReason), $check->blockedReason);
            }

            $override = false;
            if ($check->minIntervalViolation) {
                if (blank($overrideReason)) {
                    throw new BusinessRuleException(
                        __('api.contact.min_interval'),
                        'min_interval',
                        422,
                        ['override_reason' => [__('api.contact.override_required')]],
                    );
                }
                if ($check->overrideCount >= $check->maxOverrides) {
                    throw new BusinessRuleException(
                        __('api.contact.max_overrides', ['max' => $check->maxOverrides]),
                        'max_overrides',
                    );
                }
                $override = true;
            }

            $now = now();
            $attributes = [
                'last_contacted_at' => $now,
                'last_contacted_by' => $user->id,
                'handled_by' => $user->id,
            ];
            $from = $prospect->pipeline_status;
            if ($from === PipelineStatus::New) {
                $attributes['pipeline_status'] = PipelineStatus::Contacted->value;
                $attributes['status_changed_at'] = $now;
            }
            if ($prospect->contacted_at === null) {
                $attributes['contacted_at'] = $now;
            }
            // Atomic `contact_count = contact_count + 1` together with the other changed columns.
            $prospect->increment('contact_count', 1, $attributes);

            $meta = ['message' => $message, 'phone' => $check->phoneNormalized];
            if ($override) {
                $meta += ['override' => true, 'override_reason' => $overrideReason];
            }
            ProspectActivity::query()->create([
                'prospect_id' => $prospect->id,
                'user_id' => $user->id,
                'type' => ActivityType::Contacted->value,
                // Only record a transition when the status really moved (no "Dihubungi → Dihubungi").
                'from_status' => $from === $prospect->pipeline_status ? null : $from->value,
                'to_status' => $from === $prospect->pipeline_status ? null : $prospect->pipeline_status->value,
                'body' => __('api.activity.contacted'),
                'meta' => $meta,
            ]);

            return $prospect->refresh();
        });
    }
}
