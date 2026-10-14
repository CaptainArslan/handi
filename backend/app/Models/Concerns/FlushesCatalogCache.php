<?php

namespace App\Models\Concerns;

use App\Support\CatalogCache;

/**
 * Clears the catalog cache whenever the model is saved or deleted,
 * so admin edits reach the app on its next request.
 */
trait FlushesCatalogCache
{
    public static function bootFlushesCatalogCache(): void
    {
        static::saved(fn () => CatalogCache::flush());
        static::deleted(fn () => CatalogCache::flush());
    }
}
