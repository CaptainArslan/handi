<?php

namespace App\Http\Requests\Meal;

use Illuminate\Foundation\Http\FormRequest;

/** Offline changes to replay (each payload is validated like a normal request). */
class SyncRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'operations' => ['required', 'array', 'max:200'],
            'operations.*.op' => ['required', 'in:meal.create,meal.delete'],
            'operations.*.payload' => ['required', 'array'],
        ];
    }
}
