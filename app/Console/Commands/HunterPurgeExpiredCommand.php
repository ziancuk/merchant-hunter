<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ApiCache;
use App\Models\GeocodeQueueItem;
use App\Models\Prospect;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class HunterPurgeExpiredCommand extends Command
{
    protected $signature = 'hunter:purge-expired';

    protected $description = 'Purge expired Google cache fields';

    /** Google ToS (D-47): only place_id may be kept after expiry. */
    public const PLACEHOLDER_NAME = '[kedaluwarsa]';

    public function handle(): int
    {
        $now = Carbon::now();

        $expired = Prospect::query()
            ->where('provider', 'google')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $now)
            ->where('needs_refresh', false);

        $ids = (clone $expired)->pluck('id')->all();
        foreach (array_chunk($ids, 500) as $chunk) {
            Prospect::query()->whereIn('id', $chunk)->update([
                'lat' => null, 'lng' => null, 'address' => null, 'phone' => null, 'website' => null,
                'rating' => null, 'review_count' => null, 'opening_hours' => null, 'raw_tags' => null,
                'name' => self::PLACEHOLDER_NAME,
                'needs_refresh' => true,
            ]);
            // Their address is gone: nothing left to reverse geocode.
            GeocodeQueueItem::query()->whereIn('prospect_id', $chunk)->delete();
        }

        $cache = ApiCache::query()->where('expires_at', '<', $now)->delete();

        $this->info(sprintf('Purged %d expired Google prospect(s) and %d expired cache row(s).', count($ids), $cache));

        return self::SUCCESS;
    }
}
