<?php

namespace App\Models;

use App\Enums\Budget;
use App\Enums\LocationMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The user's household settings: family, budget, preferences, location and reminder. */
class Kitchen extends Model
{
    use HasFactory;

    public const DEFAULT_MEALS = ['lunch', 'dinner'];

    protected $fillable = [
        'user_id', 'family_size', 'budget', 'repeat_gap_days', 'meals',
        'location_mode', 'city_id', 'lat', 'lng', 'location_label',
        'notifications_enabled', 'notify_time', 'last_synced_at',
    ];

    protected $attributes = [
        'family_size' => 4,
        'budget' => 'normal',
        'repeat_gap_days' => 5,
        'meals' => '["lunch","dinner"]',
        'notifications_enabled' => true,
        'notify_time' => '09:00',
    ];

    protected function casts(): array
    {
        return [
            'family_size' => 'integer',
            'budget' => Budget::class,
            'repeat_gap_days' => 'integer',
            'meals' => 'array',
            'location_mode' => LocationMode::class,
            'lat' => 'float',
            'lng' => 'float',
            'notifications_enabled' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** Coordinates used for weather: GPS (rounded) or the chosen city. */
    public function coordinates(): ?array
    {
        if ($this->lat !== null && $this->lng !== null) {
            return [$this->lat, $this->lng];
        }

        if ($this->city) {
            return [$this->city->lat, $this->city->lng];
        }

        return null;
    }

    /** "Lahore" — short label for the weather strip and settings. */
    public function cityName(): ?string
    {
        if ($this->city) {
            return $this->city->name;
        }

        return $this->location_label ? trim(explode('·', $this->location_label)[0]) : null;
    }
}
