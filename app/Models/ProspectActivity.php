<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActivityType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectActivity extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['prospect_id', 'user_id', 'type', 'from_status', 'to_status', 'body', 'meta'];

    protected function casts(): array
    {
        return ['type' => ActivityType::class, 'meta' => 'array', 'created_at' => 'datetime'];
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
