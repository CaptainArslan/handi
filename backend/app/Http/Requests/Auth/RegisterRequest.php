<?php

namespace App\Http\Requests\Auth;

use App\Support\Rules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Sign up with everything chosen during onboarding. */
class RegisterRequest extends FormRequest
{
    use NormalizesEmail;

    public function rules(): array
    {
        return [
            'name' => Rules::name(),
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => [...Rules::password(), 'confirmed'],
            'password_confirmation' => ['required'],
            'terms_accepted' => ['accepted'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'timezone' => ['nullable', 'timezone:all'],
            'kitchen' => ['required', 'array'],
            ...Rules::kitchen('kitchen.'),
            'location' => ['nullable', 'array'],
            'location.mode' => ['required_with:location', Rule::in(['gps', 'city'])],
            'location.city_id' => ['required_if:location.mode,city', 'nullable', 'integer', 'exists:cities,id'],
            'location.lat' => ['required_if:location.mode,gps', 'nullable', 'numeric', 'between:-90,90'],
            'location.lng' => ['required_if:location.mode,gps', 'nullable', 'numeric', 'between:-180,180'],
            'dish_ids' => ['present', 'array', 'max:500'],
            'dish_ids.*' => ['integer', 'distinct', 'exists:dishes,id'],
            'custom_dishes' => ['nullable', 'array', 'max:50'],
            'custom_dishes.*.name' => ['required', 'string', 'min:2', 'max:40', 'regex:'.Rules::DISH_NAME_REGEX],
            'custom_dishes.*.category' => ['nullable', 'string', 'exists:categories,key'],
        ];
    }

    public function messages(): array
    {
        return [
            ...Rules::nameMessages(),
            ...Rules::emailMessages(),
            ...Rules::passwordMessages(),
            ...Rules::kitchenMessages('kitchen.'),
            'password_confirmation.required' => 'Confirm your password.',
            'terms_accepted.accepted' => 'Please accept the terms to continue.',
        ];
    }

    /** At least 10 dishes (library + custom) once everything else is valid. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                $total = count((array) $this->input('dish_ids', [])) + count((array) $this->input('custom_dishes', []));
                $min = (int) config('handi.picks.min_onboarding_dishes', 10);
                if ($total < $min) {
                    $validator->errors()->add('dish_ids', "Pick at least {$min} dishes.");
                }
            },
        ];
    }
}
