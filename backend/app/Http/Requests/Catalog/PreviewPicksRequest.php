<?php

namespace App\Http\Requests\Catalog;

use App\Support\Rules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A guest's onboarding choices, to preview today's picks before signing up. */
class PreviewPicksRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'dish_ids' => ['required', 'array', 'min:1', 'max:500'],
            'dish_ids.*' => ['integer'],
            'location' => ['nullable', 'array'],
            'location.mode' => ['required_with:location', Rule::in(['gps', 'city'])],
            'location.city_id' => ['required_if:location.mode,city', 'nullable', 'integer', 'exists:cities,id'],
            'location.lat' => ['required_if:location.mode,gps', 'nullable', 'numeric', 'between:-90,90'],
            'location.lng' => ['required_if:location.mode,gps', 'nullable', 'numeric', 'between:-180,180'],
            ...Rules::kitchen(partial: true),
        ];
    }

    public function messages(): array
    {
        return Rules::kitchenMessages();
    }
}
