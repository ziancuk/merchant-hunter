<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'name', 'weight', 'whatsapp_template', 'osm_filters', 'google_types', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'osm_filters' => 'array',
            'google_types' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function prospects(): HasMany
    {
        return $this->hasMany(Prospect::class);
    }
}
