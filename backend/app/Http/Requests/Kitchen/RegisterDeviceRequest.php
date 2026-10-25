<?php

namespace App\Http\Requests\Kitchen;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** This phone and its Expo push token. */
class RegisterDeviceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string', 'max:100'],
            'expo_push_token' => ['nullable', 'string', 'max:255', 'regex:/^Expo(nent)?PushToken\[.+\]$/'],
            'platform' => ['required', Rule::in(['android', 'ios'])],
            'name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
