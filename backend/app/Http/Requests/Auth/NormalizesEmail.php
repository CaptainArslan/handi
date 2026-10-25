<?php

namespace App\Http\Requests\Auth;

/** Lower-cases and trims `email` before validation, so "Hina@Example.com " matches the account. */
trait NormalizesEmail
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }
}
