<?php

namespace App\Http\Requests\Meal;

use App\Support\UserClock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Shared rules for logging (all required) and editing (any field) a meal. Dates are in the user's timezone. */
abstract class MealRequest extends FormRequest
{
    abstract protected function partial(): bool;

    public function rules(): array
    {
        $today = UserClock::today($this->user());
        $min = $today->subDays((int) config('handi.meals.log_days_back', 60));
        $req = $this->partial() ? 'sometimes' : 'required';

        return [
            'id' => ['nullable', 'uuid'],
            'kitchen_dish_id' => [$req, 'integer', Rule::exists('kitchen_dishes', 'id')->where('user_id', $this->user()?->id)],
            'date' => [$req, 'date_format:Y-m-d', 'before_or_equal:'.$today->toDateString(), 'after_or_equal:'.$min->toDateString()],
            'meal' => [$req, Rule::in(['breakfast', 'lunch', 'dinner'])],
            'source' => ['nullable', Rule::in(['suggestion', 'manual'])],
        ];
    }

    public function messages(): array
    {
        $days = (int) config('handi.meals.log_days_back', 60);
        $min = UserClock::today($this->user())->subDays($days);

        return [
            'kitchen_dish_id.required' => 'Pick the dish you cooked.',
            'kitchen_dish_id.exists' => 'Pick the dish you cooked.',
            'date.required' => 'Choose a date.',
            'date.date_format' => 'Choose a date.',
            'date.before_or_equal' => 'You can’t log a meal in the future.',
            'date.after_or_equal' => "You can log up to {$days} days back (from {$min->format('j M')}).",
        ];
    }
}
