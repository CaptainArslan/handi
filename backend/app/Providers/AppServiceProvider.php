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
        // Telescope (/telescope) — only when the package is installed (`composer require laravel/telescope`).
        if (class_exists(\Laravel\Telescope\TelescopeApplicationServiceProvider::class)) {
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    public function boot(): void
    {
        JsonResource::wrap('data');

        // TRUSTED_PROXIES (config/app.php): needed for https + logins through ngrok / Herd Share.
        if ($proxies = config('app.trusted_proxies')) {
            \Illuminate\Http\Middleware\TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        // A library dish photo (or its thumbnails) changed: the cached library must be rebuilt.
        $flushForDish = function (\Spatie\MediaLibrary\MediaCollections\Models\Media $media): void {
            if ($media->model_type === \App\Models\Dish::class) {
                \App\Support\CatalogCache::flush();
            }
        };
        \Spatie\MediaLibrary\MediaCollections\Models\Media::saved($flushForDish);
        \Spatie\MediaLibrary\MediaCollections\Models\Media::deleted($flushForDish);

        RateLimiter::for('api', function (Request $request) {
            $user = $request->user('sanctum');

            return $user
                ? Limit::perMinute(180)->by('user:'.$user->getKey())
                : Limit::perMinute(60)->by('ip:'.$request->ip());
        });

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(20)->by('auth:'.$request->ip()));

        // Sensitive signed-in actions that send email codes or pushes: a few per hour per user.
        RateLimiter::for('sensitive', fn (Request $request) => Limit::perHour(10)->by('sensitive:'.($request->user('sanctum')?->getKey() ?? $request->ip())));

        // Interactive API docs (/docs/api): open locally, super admins only elsewhere.
        Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi) {
            $openApi->secure(SecurityScheme::http('bearer'));
        });

        Gate::define('viewApiDocs', fn (?User $user = null) => app()->isLocal() || (bool) $user?->is_admin);
    }
}
