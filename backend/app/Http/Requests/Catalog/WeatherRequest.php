<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

/** A city, or coordinates. */
class WeatherRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'city_id' => ['required_without:lat', 'nullable', 'integer', 'exists:cities,id'],
            'lat' => ['required_without:city_id', 'nullable', 'numeric', 'between:-90,90'],
            'lng' => ['required_with:lat', 'nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
