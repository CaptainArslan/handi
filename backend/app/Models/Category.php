<?php

namespace App\Models;

use App\Models\Concerns\FlushesCatalogCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use FlushesCatalogCache, HasFactory;

    protected $fillable = ['key', 'name', 'image_key', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function dishes(): HasMany
    {
        return $this->hasMany(Dish::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
