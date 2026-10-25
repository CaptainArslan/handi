<?php

namespace App\Http\Requests\Auth;

/** Email + the 6-digit password reset code. */
class VerifyResetCodeRequest extends ResetEmailRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'code' => ['required', 'digits:6'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'code.required' => 'Enter the 6-digit code.',
            'code.digits' => 'Enter the 6-digit code.',
        ];
    }
}
