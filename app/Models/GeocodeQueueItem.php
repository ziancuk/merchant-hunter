<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeocodeQueueItem extends Model
{
    protected $table = 'geocode_queue';

    protected $fillable = ['prospect_id', 'priority', 'status', 'attempts', 'last_error', 'available_at', 'done_at'];

    protected function casts(): array
    {
        return ['available_at' => 'datetime', 'done_at' => 'datetime'];
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }
}
