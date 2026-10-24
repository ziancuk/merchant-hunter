<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Outlet;
use App\Services\Sync\WhitespaceCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class HunterDemoResetCommand extends Command
{
    protected $signature = 'hunter:demo-reset {--force : Allow running in production}';

    protected $description = 'Reset demo data to the deterministic seed';

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('Refusing to reset data in production. Pass --force if you really mean it.');

            return self::FAILURE;
        }

        DB::transaction(function () {
            DB::table('prospect_activities')->delete();
            DB::table('geocode_queue')->delete();
            DB::table('prospects')->delete();
            DB::table('scheduler_run_outlets')->delete();
            DB::table('scheduler_runs')->delete();
            Outlet::query()->update(['last_synced_at' => null, 'baseline_done_at' => null]);
        });
        WhitespaceCache::bump();
        $this->info('Prospects, activities, geocode queue and scheduler runs cleared.');

        return $this->call('hunter:seed-prospects', ['--offline' => true, '--seed' => 42]);
    }
}
