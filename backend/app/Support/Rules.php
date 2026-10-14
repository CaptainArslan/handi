<?php

namespace App\Support;

use App\Enums\Budget;
use App\Enums\Cost;
use App\Enums\Effort;
use App\Enums\MealType;
use App\Enums\UsualMeal;
use App\Enums\Weather;
use Illuminate\Validation\Rule;

/**
 * Shared validation rules and the exact error copy from the app design,
 * so server errors read the same as the app's own checks.
 */
class Rules
{
    public const NAME_REGEX = "/^[\\pL .'-]+$/u";

    public const DISH_NAME_REGEX = "/^[\\pL\\pM '’()-]+$/u";

    public static function name(): array
    {
        return ['required', 'string', 'min:2', 'max:50', 'regex:'.self::NAME_REGEX];
    }

    public static function nameMessages(string $field = 'name'): array
    {
        return [
            "$field.required" => 'Enter your name.',
            "$field.min" => 'Name is too short.',
            "$field.max" => 'Keep it under 50 characters.',
            "$field.regex" => 'Use letters and spaces only.',
        ];
    }

    public static function emailMessages(string $field = 'email'): array
    {
        return [
            "$field.required" => 'Enter your email.',
            "$field.email" => 'Enter a valid email, like name@example.com.',
            "$field.unique" => 'This email already has an account.',
        ];
    }

    /** 8+ characters with a letter and a number. */
    public static function password(): array
    {
        return ['required', 'string', 'min:8', 'max:100', 'regex:/[A-Za-z]/', 'regex:/\d/'];
    }

    public static function passwordMessages(string $field = 'password'): array
    {
        return [
            "$field.required" => 'Create a password.',
            "$field.min" => 'Use at least 8 characters.',
            "$field.regex" => 'Mix letters and at least one number.',
            "$field.confirmed" => 'Passwords don’t match.',
        ];
    }

    public static function kitchen(string $prefix = '', bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return [
            $prefix.'family_size' => [$req, 'integer', 'min:1', 'max:20'],
            $prefix.'budget' => [$req, Rule::enum(Budget::class)],
            $prefix.'repeat_gap_days' => [$req, 'integer', Rule::in(config('handi.picks.repeat_gap_options'))],
            $prefix.'meals' => [$req, 'array', 'min:1'],
            $prefix.'meals.*' => ['distinct', Rule::enum(MealType::class)],
        ];
    }

    public static function kitchenMessages(string $prefix = ''): array
    {
        return [
            $prefix.'family_size.required' => 'Enter how many people eat at home.',
            $prefix.'family_size.integer' => 'Use a whole number.',
            $prefix.'family_size.min' => 'At least 1 person.',
            $prefix.'family_size.max' => 'Maximum is 20 people.',
            $prefix.'budget.required' => 'Choose a budget to continue.',
            $prefix.'budget.enum' => 'Choose a budget to continue.',
            $prefix.'repeat_gap_days.in' => 'Choose 3, 5, 7 or 10 days.',
            $prefix.'meals.required' => 'Pick at least one meal.',
            $prefix.'meals.min' => 'Pick at least one meal.',
        ];
    }

    /** Weather tags: at least one; "any" must be alone. */
    public static function weather(string $field = 'weather', bool $required = true): array
    {
        return [
            $field => [$required ? 'required' : 'sometimes', 'array', 'min:1', function ($attribute, $value, $fail) {
                if (is_array($value) && in_array('any', $value, true) && count($value) > 1) {
                    $fail('Pick “Any” on its own, or specific weather.');
                }
            }],
            $field.'.*' => ['distinct', Rule::enum(Weather::class)],
        ];
    }

    public static function weatherMessages(string $field = 'weather', string $message = 'Pick at least one, or “Any”.'): array
    {
        return [
            "$field.required" => $message,
            "$field.min" => $message,
        ];
    }

    public static function dishTags(bool $partial = true): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return [
            'effort' => [$req, Rule::enum(Effort::class)],
            'cost' => [$req, Rule::enum(Cost::class)],
            'usual_meal' => [$req, Rule::enum(UsualMeal::class)],
        ];
    }

    /** "HH:mm" between 05:00 and 13:00. */
    public static function reminderTime(): array
    {
        return ['required', 'date_format:H:i', 'after_or_equal:'.config('handi.reminder.earliest'), 'before_or_equal:'.config('handi.reminder.latest')];
    }

    public static function reminderTimeMessages(string $field = 'time'): array
    {
        $msg = 'Pick a time between 5:00 AM and 1:00 PM.';

        return ["$field.required" => $msg, "$field.date_format" => $msg, "$field.after_or_equal" => $msg, "$field.before_or_equal" => $msg];
    }
}
