<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Versioned cache for catalog data (categories, dishes, daily suggestions).
 *
 * Any admin change bumps the version, so every cached catalog entry is
 * invalidated at once and the app sees the change on its next fetch.
 */
class CatalogCache
{
    private const VERSION_KEY = 'catalog:version';

    private const TTL_SECONDS = 86400;

    public static function remember(string $key, Closure $callback): mixed
    {
        return Cache::remember(self::key($key), self::TTL_SECONDS, $callback);
    }

    public static function key(string $key): string
    {
        return 'catalog:v'.self::version().':'.$key;
    }

    public static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }

    public static function flush(): void
    {
        Cache::forever(self::VERSION_KEY, self::version() + 1);
    }
}
