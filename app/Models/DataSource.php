<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DataSource extends Model
{
    protected $fillable = [
        'key', 'name', 'is_active', 'priority', 'api_key', 'config', 'monthly_quota',
        'cost_search_per_1000', 'cost_details_per_1000',
    ];

    /** Never serialised: the key must not leak (D-55). */
    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'priority' => 'integer',
            'api_key' => 'encrypted',
            'config' => 'array',
            'monthly_quota' => 'integer',
            'cost_search_per_1000' => 'float',
            'cost_details_per_1000' => 'float',
            'calls_this_month' => 'integer',
            'last_run_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('priority');
    }

    public function hasKey(): bool
    {
        return filled($this->api_key);
    }

    /** Four trailing characters, masked (e.g. "…a1b2"). */
    public function keyHint(): ?string
    {
        return $this->hasKey() ? '…'.substr((string) $this->api_key, -4) : null;
    }
}
