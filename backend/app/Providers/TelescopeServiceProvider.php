<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Sentinel\Drivers\Driver;
use Laravel\Sentinel\Sentinel;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

/**
 * Laravel Telescope — request, query, exception, job, mail and log inspector at /telescope.
 *
 * - On/off with TELESCOPE_ENABLED in .env (off in tests and, by default, in production).
 * - Locally it records everything; elsewhere it records only problems (exceptions, failed
 *   requests/jobs, scheduled tasks).
 * - Only the admin accounts in TELESCOPE_ALLOWED_EMAILS (default admin@handi.test) can open it.
 * - Passwords, tokens and reset codes are never stored.
 */
class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    public function register(): void
    {
        // Telescope's built-in "Sentinel" check refuses every request that comes through a tunnel while
        // APP_ENV=local (even a signed-in super admin). Access is decided in authorization() below instead.
        Sentinel::extend('telescope', fn ($app) => new class(fn () => $app) extends Driver
        {
            public function authorize(Request $request): bool
            {
                return true;
            }
        });

        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            return $isLocal
                || $entry->isReportableException()
                || $entry->isFailedRequest()
                || $entry->isFailedJob()
                || $entry->isScheduledTask()
                || $entry->hasMonitoredTag();
        });
    }

    /** Never keep secrets in Telescope, not even locally. */
    protected function hideSensitiveRequestDetails(): void
    {
        Telescope::hideRequestParameters([
            '_token', 'password', 'password_confirmation', 'current_password', 'new_password',
            'token', 'reset_token', 'code', 'expo_push_token',
        ]);

        Telescope::hideRequestHeaders(['authorization', 'cookie', 'set-cookie', 'x-csrf-token', 'x-xsrf-token']);

        Telescope::hideResponseParameters(['data.token', 'data.reset_token', 'token']);
    }

    /**
     * Who may open /telescope: only signed-in super admins whose email is in
     * TELESCOPE_ALLOWED_EMAILS (default admin@handi.test). Sign in at the admin panel first —
     * it uses the same session. There is no "open on localhost" exception.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', fn (?User $user = null) => self::allowed($user));
    }

    protected function authorization(): void
    {
        $this->gate();

        Telescope::auth(fn (Request $request): bool => Gate::forUser($request->user())->allows('viewTelescope'));
    }

    public static function allowed(?User $user): bool
    {
        return $user !== null
            && $user->is_admin
            && in_array(strtolower($user->email), config('telescope.allowed_emails', []), true);
    }
}
