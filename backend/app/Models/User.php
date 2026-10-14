<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'phone', 'password', 'timezone', 'terms_accepted_at', 'password_changed_at'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_active_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /** Only super admins can open /admin. App users sign in through the API. */
    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_admin;
    }

    public function kitchen(): HasOne
    {
        return $this->hasOne(Kitchen::class);
    }

    /** The user's kitchen, created with defaults if missing. */
    public function kitchenOrDefault(): Kitchen
    {
        if (! $this->kitchen) {
            $this->setRelation('kitchen', $this->kitchen()->create([]));
        }

        return $this->kitchen;
    }

    public function kitchenDishes(): HasMany
    {
        return $this->hasMany(KitchenDish::class);
    }

    public function mealLogs(): HasMany
    {
        return $this->hasMany(MealLog::class);
    }

    public function dailyPicks(): HasMany
    {
        return $this->hasMany(DailyPick::class);
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function firstName(): string
    {
        return Str::before(trim($this->name), ' ') ?: trim($this->name);
    }

    /** "h***@gmail.com" */
    public function maskedEmail(): string
    {
        [$local, $domain] = array_pad(explode('@', $this->email, 2), 2, '');

        return Str::substr($local, 0, 1).'***@'.$domain;
    }
}
