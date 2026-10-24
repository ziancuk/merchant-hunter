<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PipelineStatus;
use App\Models\Prospect;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Applies a status change or opt-out to up to 200 prospects, reporting what was skipped. */
final class BulkProspectAction
{
    public const MAX_IDS = 200;

    public function __construct(
        private readonly ChangeStatusAction $status,
        private readonly OptOutAction $optOut,
    ) {}

    /**
     * @param  list<int>  $ids
     * @return array{updated: int, skipped: list<array{id: int, reason: string}>}
     */
    public function handle(array $ids, string $action, User $user, ?PipelineStatus $status = null, ?int $rejectionReasonId = null): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $prospects = Prospect::query()->whereIn('id', $ids)->get()->keyBy('id');

        $updated = 0;
        $skipped = [];
        foreach ($ids as $id) {
            /** @var Prospect|null $prospect */
            $prospect = $prospects->get($id);
            if ($prospect === null) {
                $skipped[] = ['id' => $id, 'reason' => 'not_found'];

                continue;
            }

            if ($action === 'opt_out') {
                if ($prospect->do_not_contact) {
                    $skipped[] = ['id' => $id, 'reason' => 'already_opted_out'];

                    continue;
                }
                $this->optOut->optOut($prospect, $user);
                $updated++;

                continue;
            }

            if ($status === null) {
                throw ValidationException::withMessages(['status' => ['Status wajib diisi.']]);
            }
            if ($prospect->pipeline_status === $status && $status !== PipelineStatus::Lost) {
                $skipped[] = ['id' => $id, 'reason' => 'same_status'];

                continue;
            }
            $this->status->handle($prospect, $status, $user, $rejectionReasonId);
            $updated++;
        }

        return ['updated' => $updated, 'skipped' => $skipped];
    }
}
