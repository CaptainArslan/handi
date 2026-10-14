<?php

namespace App\Providers;

use App\Models\User;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        JsonResource::wrap('data');

        RateLimiter::for('api', function (Request $request) {
            $user = $request->user('sanctum');

            return $user
                ? Limit::perMinute(180)->by('user:'.$user->getKey())
                : Limit::perMinute(60)->by('ip:'.$request->ip());
        });

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(20)->by('auth:'.$request->ip()));

        // Interactive API docs (/docs/api): open locally, super admins only elsewhere.
        Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi) {
            $openApi->secure(SecurityScheme::http('bearer'));
        });

        Gate::define('viewApiDocs', fn (?User $user = null) => app()->isLocal() || (bool) $user?->is_admin);
    }
}
