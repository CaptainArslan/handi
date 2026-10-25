<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

class MarkNotificationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'read' => ['required', 'boolean'],
        ];
    }
}
