<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

class ListNotificationsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'in:all,unread'],
        ];
    }
}
