<?php

namespace App\Models;

use App\Enums\Cost;
use App\Enums\Effort;
use App\Enums\UsualMeal;
use App\Models\Concerns\FlushesCatalogCache;
use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A dish in the global library. Users add library dishes to their kitchen. */
class Dish extends Model
{
    use FlushesCatalogCache, HasFactory, HasSlug;

    protected $fillable = [
        'name', 'slug', 'category_id', 'note', 'description', 'image', 'image_key',
        'default_weather', 'default_effort', 'default_cost', 'usual_meal', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_weather' => 'array',
            'default_effort' => Effort::class,
            'default_cost' => Cost::class,
            'usual_meal' => UsualMeal::class,
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function kitchenDishes(): HasMany
    {
        return $this->hasMany(KitchenDish::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
