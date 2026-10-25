<?php

namespace App\Http\Requests\Meal;

class StoreMealRequest extends MealRequest
{
    protected function partial(): bool
    {
        return false;
    }
}
