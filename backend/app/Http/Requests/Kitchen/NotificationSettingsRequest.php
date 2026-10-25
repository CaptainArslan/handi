<?php

namespace App\Http\Requests\Kitchen;

use App\Support\Rules;
use Illuminate\Foundation\Http\FormRequest;

/** Daily reminder on/off and time (05:00–13:00). */
class NotificationSettingsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'time' => Rules::reminderTime(),
        ];
    }

    public function messages(): array
    {
        return Rules::reminderTimeMessages();
    }
}
