<?php

namespace App\Http\Requests\Dish;

use App\Support\Rules;
use Illuminate\Foundation\Http\FormRequest;

/** Your own weather / effort / cost for a dish. */
class UpdateDishTagsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            ...Rules::weather(required: false),
            'effort' => ['sometimes', 'in:quick,medium,heavy'],
            'cost' => ['sometimes', 'in:low,medium,high'],
        ];
    }

    public function messages(): array
    {
        return Rules::weatherMessages(message: 'Pick at least one weather, or “Any”.');
    }
}
