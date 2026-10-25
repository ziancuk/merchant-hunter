<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LifecycleStatus;
use App\Enums\PipelineStatus;
use App\Enums\ScoreBand;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Prospect extends Model
{
    use HasFactory;

    /** Pipeline/contact fields are changed only via Actions, never mass-assigned from requests. */
    protected $fillable = [
        'provider', 'place_id', 'name', 'category_id', 'lat', 'lng', 'province_id', 'city',
        'address', 'address_source', 'phone', 'phone_simulated', 'website', 'rating',
        'rating_simulated', 'review_count', 'reviews_simulated', 'opening_hours', 'is_simulated',
        'nearest_outlet_id', 'nearest_outlet_distance_m', 'neighbor_count_500m', 'area_density',
        'score', 'score_breakdown', 'is_new_open', 'is_baseline', 'first_seen_at', 'last_seen_at',
        'missed_runs', 'lifecycle_status', 'closed_at', 'closed_reason', 'fetched_at', 'expires_at',
        'needs_refresh', 'raw_tags',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'rating' => 'float',
            'review_count' => 'integer',
            'phone_simulated' => 'boolean',
            'rating_simulated' => 'boolean',
            'reviews_simulated' => 'boolean',
            'is_simulated' => 'boolean',
            'is_new_open' => 'boolean',
            'is_baseline' => 'boolean',
            'do_not_contact' => 'boolean',
            'needs_refresh' => 'boolean',
            'score' => 'integer',
            'score_breakdown' => 'array',
            'raw_tags' => 'array',
            'lifecycle_status' => LifecycleStatus::class,
            'pipeline_status' => PipelineStatus::class,
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'closed_at' => 'datetime',
            'status_changed_at' => 'datetime',
            'contacted_at' => 'datetime',
            'demo_at' => 'datetime',
            'won_at' => 'datetime',
            'lost_at' => 'datetime',
            'last_contacted_at' => 'datetime',
            'do_not_contact_at' => 'datetime',
            'fetched_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function scoreBand(): ScoreBand
    {
        return ScoreBand::fromScore((int) $this->score);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function nearestOutlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'nearest_outlet_id');
    }

    public function rejectionReason(): BelongsTo
    {
        return $this->belongsTo(RejectionReason::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function lastContactedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_contacted_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ProspectActivity::class);
    }

    public function geocodeQueueItem(): HasOne
    {
        return $this->hasOne(GeocodeQueueItem::class);
    }
}
