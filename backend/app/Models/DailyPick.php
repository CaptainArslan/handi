<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A user's 3 picks for one day, the shuffle count and the chosen plan. */
class DailyPick extends Model
{
    protected $fillable = [
        'user_id', 'date', 'set_number', 'shuffles_used', 'items', 'weather', 'generated_at',
        'plan_kitchen_dish_id', 'plan_meal', 'planned_at', 'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'set_number' => 'integer',
            'shuffles_used' => 'integer',
            'items' => 'array',
            'weather' => 'array',
            'generated_at' => 'datetime',
            'planned_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    /** Stored as plain Y-m-d so lookups behave the same on MySQL and SQLite. */
    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? CarbonImmutable::parse($value)->startOfDay() : null,
            set: fn (mixed $value) => $value instanceof DateTimeInterface
                ? $value->format('Y-m-d')
                : CarbonImmutable::parse((string) $value)->format('Y-m-d'),
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function planDish(): BelongsTo
    {
        return $this->belongsTo(KitchenDish::class, 'plan_kitchen_dish_id');
    }

    /** @return array<int, int> */
    public function dishIds(): array
    {
        return array_map(fn (array $item) => (int) $item['kitchen_dish_id'], $this->items ?? []);
    }
}
