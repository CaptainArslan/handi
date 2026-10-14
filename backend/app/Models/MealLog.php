<?php

namespace App\Models;

use App\Enums\LogSource;
use App\Enums\MealType;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MealLog extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'id', 'user_id', 'kitchen_dish_id', 'dish_name', 'category_key', 'date', 'meal', 'source', 'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'meal' => MealType::class,
            'source' => LogSource::class,
            'logged_at' => 'datetime',
        ];
    }

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

    public function kitchenDish(): BelongsTo
    {
        return $this->belongsTo(KitchenDish::class);
    }
}
