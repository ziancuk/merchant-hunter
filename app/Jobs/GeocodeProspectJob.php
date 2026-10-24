<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\GeocodeQueueItem;
use App\Models\Prospect;
use App\Services\Geocoding\ReverseGeocodeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Reverse-geocodes one prospect from its geocode_queue row (the shared 4 req/min limit still applies). */
class GeocodeProspectJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public int $prospectId;

    public function __construct(int|Prospect $prospect)
    {
        $this->prospectId = $prospect instanceof Prospect ? $prospect->id : $prospect;
    }

    public function handle(ReverseGeocodeService $service): void
    {
        if (! $service->available()) {
            return;
        }
        $item = GeocodeQueueItem::query()->where('prospect_id', $this->prospectId)->where('status', 'pending')->first();
        if ($item !== null) {
            $service->processItem($item);
        }
    }
}
