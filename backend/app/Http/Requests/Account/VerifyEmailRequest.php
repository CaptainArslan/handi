<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

/** The 6-digit code sent to the new email address. */
class VerifyEmailRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'digits:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Enter the 6-digit code.',
            'code.digits' => 'Enter the 6-digit code.',
        ];
    }
}
