<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Sync\ProspectRescorer;
use App\Services\Sync\WhitespaceCache;
use Illuminate\Console\Command;

class HunterScoreCommand extends Command
{
    protected $signature = 'hunter:score {--all}';

    protected $description = 'Recompute prospect scores';

    public function handle(ProspectRescorer $rescorer): int
    {
        $stats = $rescorer->run((bool) $this->option('all'));
        WhitespaceCache::bump();

        $this->info(sprintf(
            'Scored %d prospect(s), %d changed; %d "new open" flag(s) expired; %d location metric(s) refreshed.',
            $stats['scored'], $stats['changed'], $stats['expired_new_open'], $stats['metrics_changed'],
        ));

        return self::SUCCESS;
    }
}
