<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ActivityType;
use App\Enums\PipelineStatus;
use App\Models\Prospect;
use App\Models\ProspectActivity;
use App\Models\RejectionReason;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Moves a prospect through the pipeline and records who/when (stage timestamps only the first time). */
final class ChangeStatusAction
{
    /** Stage timestamps that are (back)filled when a stage is reached, so the funnel stays monotonic. */
    private const STAGE_COLUMNS = [
        'contacted' => ['contacted_at'],
        'demo' => ['contacted_at', 'demo_at'],
        'won' => ['contacted_at', 'demo_at', 'won_at'],
        'lost' => ['lost_at'],
        'new' => [],
    ];

    public function handle(Prospect $prospect, PipelineStatus $to, User $user, ?int $rejectionReasonId = null): Prospect
    {
        $reason = null;
        if ($to === PipelineStatus::Lost) {
            $reason = $rejectionReasonId !== null ? RejectionReason::query()->find($rejectionReasonId) : null;
            if ($reason === null) {
                throw ValidationException::withMessages(['rejection_reason_id' => ['Alasan penolakan wajib dipilih bila status Ditolak.']]);
            }
        }

        $from = $prospect->pipeline_status;
        if ($from === $to && ($to !== PipelineStatus::Lost || $prospect->rejection_reason_id === $reason?->id)) {
            return $prospect;
        }

        return DB::transaction(function () use ($prospect, $to, $user, $from, $reason) {
            $now = now();
            $attributes = [
                'pipeline_status' => $to->value,
                'status_changed_at' => $now,
                'handled_by' => $user->id,
                'rejection_reason_id' => $reason?->id,
            ];
            foreach (self::STAGE_COLUMNS[$to->value] as $column) {
                if ($prospect->{$column} === null) {
                    $attributes[$column] = $now;
                }
            }
            $prospect->forceFill($attributes)->save();

            ProspectActivity::query()->create([
                'prospect_id' => $prospect->id,
                'user_id' => $user->id,
                'type' => $reason !== null ? ActivityType::Rejected->value : ActivityType::StatusChange->value,
                'from_status' => $from->value,
                'to_status' => $to->value,
                'body' => $reason !== null ? 'Ditolak: '.$reason->label : null,
                'meta' => $reason !== null ? ['rejection_reason_id' => $reason->id, 'rejection_reason_key' => $reason->key] : null,
            ]);

            return $prospect->refresh();
        });
    }
}
