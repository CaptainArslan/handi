<?php

namespace App\Http\Requests\Account;

use App\Support\Rules;
use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => [...Rules::password(), 'confirmed', 'different:current_password'],
            'password_confirmation' => ['required'],
            'sign_out_other_devices' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Enter your current password.',
            'password.required' => 'Meet all the rules below.',
            'password.min' => 'Meet all the rules below.',
            'password.regex' => 'Meet all the rules below.',
            'password.different' => 'Meet all the rules below.',
            'password.confirmed' => 'Passwords don’t match.',
            'password_confirmation.required' => 'Confirm your new password.',
        ];
    }
}
