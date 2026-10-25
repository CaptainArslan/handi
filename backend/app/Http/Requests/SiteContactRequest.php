<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\ViewErrorBag;

/**
 * The website's contact form. Same rules as the app, but the site has no session,
 * so on errors the page is shown again right away with the messages and what was typed.
 */
class SiteContactRequest extends ContactRequest
{
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->view('site.contact', [
            'errors' => (new ViewErrorBag)->put('default', $validator->errors()),
            'old' => $this->except('attachment'),
            'sent' => false,
        ], 422));
    }
}
