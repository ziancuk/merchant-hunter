<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchedulerRunOutlet extends Model
{
    protected $fillable = [
        'scheduler_run_id', 'outlet_id', 'provider', 'status', 'fetched_count', 'new_count',
        'updated_count', 'closed_count', 'error', 'duration_ms',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(SchedulerRun::class, 'scheduler_run_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}
