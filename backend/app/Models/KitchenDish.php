<?php

namespace App\Models;

use App\Enums\Cost;
use App\Enums\Effort;
use App\Enums\PhotoStatus;
use App\Enums\UsualMeal;
use App\Models\Concerns\HasDishPhoto;
use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;

/**
 * A dish in a user's kitchen: either a library dish (with optional tag overrides)
 * or a custom dish the user created. "Effective" accessors merge both cases.
 */
class KitchenDish extends Model implements HasMedia
{
    use HasDishPhoto, HasFactory;

    /** The user's own photo is part of every dish card, so load it with the dish (no N+1). */
    protected $with = ['media'];

    protected $fillable = [
        'user_id', 'dish_id', 'is_custom', 'name', 'category_id', 'usual_meal',
        'weather', 'effort', 'cost', 'photo_path', 'photo_status', 'removed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_custom' => 'boolean',
            'usual_meal' => UsualMeal::class,
            'weather' => 'array',
            'effort' => Effort::class,
            'cost' => Cost::class,
            'photo_status' => PhotoStatus::class,
            'removed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    /** Custom dishes have their own category; library dishes use the dish's. */
    public function ownCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function mealLogs(): HasMany
    {
        return $this->hasMany(MealLog::class);
    }

    public function scopeInKitchen(Builder $query): void
    {
        $query->whereNull('removed_at');
    }

    public function scopeWithDetails(Builder $query): void
    {
        $query->with(['dish.category', 'ownCategory']);
    }

    public function effectiveName(): string
    {
        return $this->is_custom ? (string) $this->name : (string) $this->dish?->name;
    }

    public function effectiveCategory(): ?Category
    {
        return $this->is_custom ? $this->ownCategory : $this->dish?->category;
    }

    /** @return array<int, string> */
    public function effectiveWeather(): array
    {
        return $this->weather ?? ($this->dish?->default_weather ?? ['any']);
    }

    public function effectiveEffort(): Effort
    {
        return $this->effort ?? $this->dish?->default_effort ?? Effort::Medium;
    }

    public function effectiveCost(): Cost
    {
        return $this->cost ?? $this->dish?->default_cost ?? Cost::Low;
    }

    public function effectiveUsualMeal(): UsualMeal
    {
        return $this->usual_meal ?? $this->dish?->usual_meal ?? UsualMeal::Both;
    }

    public function hasOverrides(): bool
    {
        return ! $this->is_custom && ($this->weather !== null || $this->effort !== null || $this->cost !== null);
    }

    /** Bundled art key: own category default for custom dishes, else the library dish's. */
    public function imageKey(): ?string
    {
        if (! $this->is_custom && $this->dish?->image_key) {
            return $this->dish->image_key;
        }

        return $this->effectiveCategory()?->image_key;
    }

    public function photoCollection(): string
    {
        return 'photo';
    }

    /** True when the user added their own photo (media library or a legacy stored file). */
    public function hasOwnPhoto(): bool
    {
        return $this->hasPhoto() || $this->photo_path !== null;
    }

    /**
     * Picture of this dish in a size (`card` default, `thumb` for lists): the user's photo,
     * then the library dish's photo, then the bundled art.
     */
    public function imageUrl(string $conversion = 'card'): ?string
    {
        if ($url = $this->photoUrl($conversion)) {
            return $url;
        }

        if ($this->photo_path && $this->photo_status === PhotoStatus::Ready) {
            return Media::url($this->photo_path); // stored before the media library
        }

        if (! $this->is_custom && $this->dish) {
            if ($url = $this->dish->photoUrl($conversion) ?? ($this->dish->image ? Media::url($this->dish->image) : null)) {
                return $url;
            }
        }

        return Media::artUrl($this->imageKey());
    }
}
