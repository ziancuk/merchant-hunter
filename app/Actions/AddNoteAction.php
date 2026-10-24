<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ActivityType;
use App\Models\Prospect;
use App\Models\ProspectActivity;
use App\Models\User;

final class AddNoteAction
{
    public function handle(Prospect $prospect, User $user, string $body): ProspectActivity
    {
        return ProspectActivity::query()->create([
            'prospect_id' => $prospect->id,
            'user_id' => $user->id,
            'type' => ActivityType::Note->value,
            'body' => $body,
        ])->load('user:id,name');
    }
}
