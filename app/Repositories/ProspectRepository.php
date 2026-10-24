<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\ActivityType;
use App\Models\GeocodeQueueItem;
use App\Models\Prospect;
use App\Models\ProspectActivity;

final class ProspectRepository
{
    public const ACTIVITY_LIMIT = 50;

    /** Loads everything ProspectDetail needs; touches the geocode queue priority with a plain DB write (D-42). */
    public function detail(Prospect $prospect): Prospect
    {
        $prospect->load([
            'category:id,key,name',
            'nearestOutlet:id,name',
            'rejectionReason:id,key,label',
            'handler:id,name',
            'lastContactedBy:id,name',
        ]);

        $prospect->setRelation(
            'activities',
            $prospect->activities()->with('user:id,name')->orderByDesc('created_at')->orderByDesc('id')->limit(self::ACTIVITY_LIMIT)->get(),
        );

        $prospect->setAttribute('latest_note', ProspectActivity::query()
            ->where('prospect_id', $prospect->id)->where('type', ActivityType::Note->value)
            ->orderByDesc('created_at')->orderByDesc('id')->value('body'));

        if (blank($prospect->address)) {
            GeocodeQueueItem::query()
                ->where('prospect_id', $prospect->id)->where('status', 'pending')->where('priority', '<', 100)
                ->update(['priority' => 100]);
        }

        return $prospect;
    }
}
