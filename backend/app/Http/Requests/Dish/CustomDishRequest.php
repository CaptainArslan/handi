<?php

namespace App\Http\Requests\Dish;

use App\Services\KitchenService;
use App\Support\Rules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Shared rules for adding (all required) and editing (any field) a custom dish. */
abstract class CustomDishRequest extends FormRequest
{
    /** Editing: fields are optional and the dish's own name doesn't count as taken. */
    abstract protected function editing(): bool;

    /** Multipart forms may send `weather` as JSON or "cold,rainy". */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('weather'))) {
            $this->merge(['weather' => json_decode($this->input('weather'), true) ?? explode(',', $this->input('weather'))]);
        }
    }

    public function rules(): array
    {
        $req = $this->editing() ? 'sometimes' : 'required';

        return [
            'name' => [$req, 'string', 'min:2', 'max:40', 'regex:'.Rules::DISH_NAME_REGEX],
            'category' => [$req, 'string', 'exists:categories,key'],
            ...Rules::weather(required: ! $this->editing()),
            'usual_meal' => ['sometimes', 'in:breakfast,lunch,dinner,both'],
            'effort' => ['sometimes', 'in:quick,medium,heavy'],
            'cost' => ['sometimes', 'in:low,medium,high'],
            'photo' => ['nullable', 'image', 'max:'.config('handi.photo_max_kb', 10240)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Give your dish a name.',
            'name.min' => 'Name is too short.',
            'name.max' => 'Keep it under 40 characters.',
            'name.regex' => 'Use letters, spaces and ( ) - only.',
            'category.required' => 'Choose a category.',
            'category.exists' => 'Choose a category.',
            ...Rules::weatherMessages(),
            'photo.max' => 'Photos can be up to 10 MB.',
            'photo.uploaded' => 'That photo is too large to upload. Try a smaller one.',
        ];
    }

    /** No two dishes with the same name in one kitchen (checked once everything else is valid). */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty() || ! is_string($this->input('name'))) {
                    return;
                }
                $name = KitchenService::cleanName($this->input('name'));
                $ignore = $this->editing() ? (int) $this->route('id') : null;
                if (app(KitchenService::class)->nameTaken($this->user(), $name, $ignore)) {
                    $validator->errors()->add('name', "“{$name}” is already in your dishes.");
                }
            },
        ];
    }
}
