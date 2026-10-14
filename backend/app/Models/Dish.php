<?php

namespace App\Models;

use App\Enums\Cost;
use App\Enums\Effort;
use App\Enums\UsualMeal;
use App\Models\Concerns\FlushesCatalogCache;
use App\Models\Concerns\HasDishPhoto;
use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Support\Media;
use Spatie\MediaLibrary\HasMedia;

/** A dish in the global library. Users add library dishes to their kitchen. */
class Dish extends Model implements HasMedia
{
    use FlushesCatalogCache, HasDishPhoto, HasFactory, HasSlug;

    /** Photos are shown with every list of dishes, so load them with the dish (no N+1). */
    protected $with = ['media'];

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

    public function photoCollection(): string
    {
        return 'image';
    }

    public function artKey(): ?string
    {
        return $this->image_key ?? $this->category?->image_key;
    }

    /**
     * Picture of this library dish: the uploaded photo (media library), a photo stored before the
     * media library (legacy `image` column), or the bundled art.
     */
    public function imageUrl(string $conversion = 'card'): ?string
    {
        return $this->photoUrl($conversion)
            ?? ($this->image ? Media::url($this->image) : null)
            ?? Media::artUrl($this->artKey());
    }
}
