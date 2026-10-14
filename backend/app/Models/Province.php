<?php

namespace App\Models;

use App\Models\Concerns\FlushesCatalogCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model
{
    use FlushesCatalogCache;

    protected $fillable = ['name', 'short_name', 'sort_order'];

    public function cities(): HasMany
    {
        return $this->hasMany(City::class)->orderBy('sort_order')->orderBy('name');
    }
}
