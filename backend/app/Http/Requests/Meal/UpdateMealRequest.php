<?php

namespace App\Http\Requests\Meal;

class UpdateMealRequest extends MealRequest
{
    protected function partial(): bool
    {
        return true;
    }
}
