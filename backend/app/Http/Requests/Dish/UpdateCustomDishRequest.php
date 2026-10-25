<?php

namespace App\Http\Requests\Dish;

class UpdateCustomDishRequest extends CustomDishRequest
{
    protected function editing(): bool
    {
        return true;
    }
}
