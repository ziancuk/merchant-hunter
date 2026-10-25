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
    private const BLOCK_MESSAGES = [
        'do_not_contact' => 'Prospek ini meminta tidak dihubungi (opt-out).',
        'closed' => 'Usaha ini sudah tutup.',
        'no_phone' => 'Prospek ini tidak memiliki nomor telepon.',
        'invalid_phone' => 'Nomor telepon bukan nomor ponsel yang valid untuk WhatsApp.',
    ];

    public function __construct(private readonly ContactPolicy $policy) {}

    public function handle(Prospect $prospect, User $user, string $message, ?string $overrideReason = null): Prospect
    {
        return DB::transaction(function () use ($prospect, $user, $message, $overrideReason) {
            // Re-read under a row lock so two concurrent contacts cannot both pass the interval/override checks
            // nor lose a contact_count increment (no-op on SQLite, effective on MySQL/PostgreSQL).
            $prospect = Prospect::query()->lockForUpdate()->findOrFail($prospect->id);
            $check = $this->policy->check($prospect, $user);

            if ($check->blockedReason !== null) {
                throw new BusinessRuleException(self::BLOCK_MESSAGES[$check->blockedReason], $check->blockedReason);
            }

            $override = false;
            if ($check->minIntervalViolation) {
                if (blank($overrideReason)) {
                    throw new BusinessRuleException(
                        'Prospek ini sudah dihubungi dalam batas waktu minimum. Isi alasan override untuk menghubungi lagi.',
                        'min_interval',
                        422,
                        ['override_reason' => ['Alasan override wajib diisi.']],
                    );
                }
                if ($check->overrideCount >= $check->maxOverrides) {
                    throw new BusinessRuleException(
                        'Batas override kontak untuk prospek ini sudah tercapai ('.$check->maxOverrides.').',
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
                'from_status' => $from->value,
                'to_status' => $prospect->pipeline_status->value,
                'body' => 'Dihubungi lewat WhatsApp',
                'meta' => $meta,
            ]);

            return $prospect->refresh();
        });
    }
}
