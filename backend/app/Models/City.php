<?php

namespace App\Models;

use App\Models\Concerns\FlushesCatalogCache;
use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class City extends Model
{
    use FlushesCatalogCache, HasFactory, HasSlug;

    protected $fillable = ['province_id', 'name', 'slug', 'lat', 'lng', 'is_popular', 'sort_order'];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'is_popular' => 'boolean',
        ];
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /** "Lahore, Punjab" */
    public function label(): string
    {
        return $this->name.', '.($this->province?->short_name ?? '');
    }

    public function kitchens(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Kitchen::class);
    }
}
