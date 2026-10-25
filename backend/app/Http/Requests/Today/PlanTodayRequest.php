<?php

namespace App\Http\Requests\Today;

use App\Services\PickService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Plan one of today's three picks for lunch or dinner. */
class PlanTodayRequest extends FormRequest
{
    public function rules(): array
    {
        $pick = app(PickService::class)->today($this->user());

        return [
            'kitchen_dish_id' => ['required', 'integer', Rule::in($pick->dishIds())],
            'meal' => ['required', Rule::in(['lunch', 'dinner'])],
        ];
    }

    public function messages(): array
    {
        return ['kitchen_dish_id.in' => 'Pick one of today’s dishes.'];
    }
}
