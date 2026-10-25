<?php

namespace App\Http\Requests\Dish;

use Illuminate\Foundation\Http\FormRequest;

class AddLibraryDishesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'library_dish_ids' => ['required', 'array', 'max:500', 'min:1'],
            'library_dish_ids.*' => ['integer', 'exists:dishes,id'],
        ];
    }
}
