<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ActivityType;
use App\Models\Prospect;
use App\Models\ProspectActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class OptOutAction
{
    public function optOut(Prospect $prospect, User $user): Prospect
    {
        return $this->set($prospect, $user, true);
    }

    public function optIn(Prospect $prospect, User $user): Prospect
    {
        return $this->set($prospect, $user, false);
    }

    private function set(Prospect $prospect, User $user, bool $optOut): Prospect
    {
        if ($prospect->do_not_contact === $optOut) {
            return $prospect;
        }

        return DB::transaction(function () use ($prospect, $user, $optOut) {
            $prospect->forceFill([
                'do_not_contact' => $optOut,
                'do_not_contact_at' => $optOut ? now() : null,
                'do_not_contact_by' => $optOut ? $user->id : null,
            ])->save();

            ProspectActivity::query()->create([
                'prospect_id' => $prospect->id,
                'user_id' => $user->id,
                'type' => ($optOut ? ActivityType::OptOut : ActivityType::OptIn)->value,
                'body' => $optOut ? __('api.activity.opt_out') : __('api.activity.opt_in'),
            ]);

            return $prospect->refresh();
        });
    }
}
