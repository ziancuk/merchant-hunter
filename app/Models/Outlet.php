<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GeocodeStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Outlet extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'address', 'city', 'province_id', 'lat', 'lng', 'place_id',
        'geocode_source', 'geocode_status', 'geocode_score', 'geocode_candidates',
        'is_active', 'is_simulated',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'geocode_status' => GeocodeStatus::class,
            'geocode_score' => 'integer',
            'geocode_candidates' => 'array',
            'is_active' => 'boolean',
            'is_simulated' => 'boolean',
            'last_synced_at' => 'datetime',
            'baseline_done_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeLocated(Builder $query): Builder
    {
        return $query->whereNotNull('lat')->whereNotNull('lng');
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function prospects(): HasMany
    {
        return $this->hasMany(Prospect::class, 'nearest_outlet_id');
    }
}
