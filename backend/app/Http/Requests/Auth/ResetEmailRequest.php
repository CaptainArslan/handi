<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/** Send or resend a password reset code. */
class ResetEmailRequest extends FormRequest
{
    use NormalizesEmail;

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Enter the email you signed up with.',
            'email.email' => 'Enter a valid email, like name@example.com.',
        ];
    }
}
