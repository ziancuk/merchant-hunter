<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyDigest extends Model
{
    protected $fillable = ['digest_date', 'payload', 'emailed_at'];

    protected function casts(): array
    {
        return ['digest_date' => 'date', 'payload' => 'array', 'emailed_at' => 'datetime'];
    }
}
