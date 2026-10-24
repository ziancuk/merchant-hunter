<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchedulerRun extends Model
{
    protected $fillable = [
        'command', 'status', 'started_at', 'finished_at', 'duration_ms', 'outlets_total',
        'outlets_ok', 'outlets_failed', 'prospects_new', 'prospects_updated', 'prospects_closed',
        'api_calls', 'error', 'details',
    ];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime', 'details' => 'array'];
    }

    public function outlets(): HasMany
    {
        return $this->hasMany(SchedulerRunOutlet::class);
    }
}
