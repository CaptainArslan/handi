<?php

namespace App\Http\Requests\Kitchen;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetLocationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(['gps', 'city'])],
            'city_id' => ['required_if:mode,city', 'nullable', 'integer', 'exists:cities,id'],
            'lat' => ['required_if:mode,gps', 'nullable', 'numeric', 'between:-90,90'],
            'lng' => ['required_if:mode,gps', 'nullable', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return ['city_id.required_if' => 'Select a city to continue.'];
    }
}
