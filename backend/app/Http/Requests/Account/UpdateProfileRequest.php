<?php

namespace App\Http\Requests\Account;

use App\Http\Requests\Auth\NormalizesEmail;
use App\Support\Rules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    use NormalizesEmail;

    public function rules(): array
    {
        return [
            'name' => ['sometimes', ...Rules::name()],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()?->id)],
            'current_password' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [...Rules::nameMessages(), ...Rules::emailMessages()];
    }
}
