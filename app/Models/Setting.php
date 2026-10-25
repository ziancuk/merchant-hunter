<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Settings\SettingsService;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => SettingsService::invalidate());
        static::deleted(fn () => SettingsService::invalidate());
    }

    public static function read(string $key, mixed $default = null): mixed
    {
        $row = static::query()->find($key);

        return $row ? $row->value : $default;
    }

    public static function write(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
