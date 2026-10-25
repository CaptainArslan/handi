<?php

namespace App\Http\Requests\Dish;

use Illuminate\Foundation\Http\FormRequest;

class DishPhotoRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'photo' => ['required', 'image', 'max:'.config('handi.photo_max_kb', 10240)],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.max' => 'Photos can be up to 10 MB.',
            'photo.uploaded' => 'That photo is too large to upload. Try a smaller one.',
            'photo.image' => 'Choose a photo (JPG, PNG or WebP).',
        ];
    }
}
