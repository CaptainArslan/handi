<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

/** Search the dish library (also used for "My dishes"). */
class LibraryDishesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:40'],
            'category' => ['nullable', 'string', 'max:40'],
        ];
    }
}
