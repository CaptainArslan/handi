<?php

namespace App\Http\Requests\Dish;

class StoreCustomDishRequest extends CustomDishRequest
{
    protected function editing(): bool
    {
        return false;
    }
}
