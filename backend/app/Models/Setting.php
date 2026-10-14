<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Runtime settings edited from the admin panel. Values are JSON and cached
 * until the next save.
 *
 * @property string $key
 * @property array<string, mixed>|null $value
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    /** @return array<string, mixed>|null */
    public static function read(string $key): ?array
    {
        return Cache::rememberForever(self::cacheKey($key), fn () => static::query()->find($key)?->value);
    }

    /** @param  array<string, mixed>|null  $value */
    public static function write(string $key, ?array $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::cacheKey($key));
    }

    public static function updatedAt(string $key): ?string
    {
        return static::query()->find($key)?->updated_at?->toIso8601String();
    }

    private static function cacheKey(string $key): string
    {
        return 'setting:'.$key;
    }
}
