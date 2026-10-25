<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** "Contact us" form: one optional file up to 2 MB. */
class ContactRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'topic' => ['required', 'string', Rule::in(array_keys(config('handi.contact.topics')))],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
            // Both the real file type (mimes) and the file name's extension (extensions) must be allowed.
            'attachment' => ['nullable', 'file', 'max:'.(int) config('handi.contact.attachment_max_kb', 2048), 'mimes:'.implode(',', config('handi.contact.attachment_mimes')), 'extensions:'.implode(',', config('handi.contact.attachment_mimes'))],
            'device' => ['nullable', 'string', 'max:60'],
            'app_version' => ['nullable', 'string', 'max:30'],
            // Honeypot: hidden on the form, only bots fill it in.
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Enter your name.',
            'name.min' => 'Name is too short.',
            'email.required' => 'Enter your email so we can reply.',
            'email.email' => 'Enter a valid email, like name@example.com.',
            'topic.required' => 'Choose what it’s about.',
            'topic.in' => 'Choose what it’s about.',
            'message.required' => 'Write your message.',
            'message.min' => 'Tell us a little more (at least 10 characters).',
            'message.max' => 'Keep it under 3,000 characters.',
            'attachment.max' => 'Files can be up to 2 MB.',
            'attachment.uploaded' => 'That file is too large to upload. Files can be up to 2 MB.',
            'attachment.mimes' => 'Attach an image, PDF, TXT or Word file.',
            'attachment.extensions' => 'Attach an image, PDF, TXT or Word file.',
        ];
    }
}
