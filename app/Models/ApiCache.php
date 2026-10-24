<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiCache extends Model
{
    protected $table = 'api_cache';

    protected $fillable = ['cache_key', 'provider', 'payload', 'fetched_at', 'expires_at'];

    protected function casts(): array
    {
        return ['fetched_at' => 'datetime', 'expires_at' => 'datetime'];
    }
}
