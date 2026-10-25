<?php

namespace App\Http\Requests\Auth;

use App\Support\Rules;
use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'reset_token' => ['required', 'string'],
            'password' => [...Rules::password(), 'regex:/^\S(.*\S)?$/s', 'confirmed'],
            'password_confirmation' => ['required'],
        ];
    }

    public function messages(): array
    {
        return [
            ...Rules::passwordMessages(),
            'password.regex' => 'Use 8+ characters with a letter and a number, and no spaces at the start or end.',
            'password_confirmation.required' => 'Confirm your new password.',
        ];
    }
}
