<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class DeleteAccountRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required', 'string'],
            'confirmation' => ['required', 'in:DELETE'],
        ];
    }

    public function messages(): array
    {
        return [
            'password.required' => 'Enter your password.',
            'confirmation.required' => 'Type DELETE in capital letters.',
            'confirmation.in' => 'It must say DELETE exactly.',
        ];
    }
}
