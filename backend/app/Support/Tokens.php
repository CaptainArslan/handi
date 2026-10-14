<?php

namespace App\Support;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class Tokens
{
    /** Id of the Sanctum token used for this request (null for session / transient auth). */
    public static function currentId(?User $user): ?int
    {
        $token = $user?->currentAccessToken();

        return $token instanceof PersonalAccessToken ? $token->id : null;
    }
}
