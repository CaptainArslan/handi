<?php

namespace App\Http\Requests\Kitchen;

use App\Support\Rules;
use Illuminate\Foundation\Http\FormRequest;

/** Any of family size, budget, repeat gap and meals. */
class UpdateKitchenRequest extends FormRequest
{
    public function rules(): array
    {
        return Rules::kitchen(partial: true);
    }

    public function messages(): array
    {
        return Rules::kitchenMessages();
    }
}
