<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Sentinel\Drivers\Driver;
use Laravel\Sentinel\Sentinel;
use Symfony\Component\HttpFoundation\IpUtils;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

/**
 * Laravel Telescope — request, query, exception, job, mail and log inspector at /telescope.
 *
 * - On/off with TELESCOPE_ENABLED in .env (off in tests and, by default, in production).
 * - Locally it records everything and is open on the machine; elsewhere it records only
 *   problems (exceptions, failed requests/jobs, scheduled tasks) and only super admins can open it.
 * - Passwords, tokens and reset codes are never stored.
 * - Through a tunnel (ngrok, Herd Share/Expose) it is never open: sign in at /admin as a super admin first.
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
     * Who may open /telescope outside the local environment: signed-in super admins
     * (sign in at /admin first — it uses the same session).
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', fn (?User $user = null) => (bool) $user?->is_admin);
    }

    /**
     * Open without login only on this machine in local mode (a direct request, no proxy/tunnel
     * headers, from a private IP). Everyone else — any tunnel, any non-local environment — must be
     * a signed-in super admin.
     */
    protected function authorization(): void
    {
        $this->gate();

        Telescope::auth(function (Request $request): bool {
            $direct = $this->app->environment('local')
                && ! $request->headers->has('X-Forwarded-For')
                && ! $request->headers->has('X-Forwarded-Host')
                && IpUtils::isPrivateIp((string) $request->server('REMOTE_ADDR'));

            return $direct || Gate::forUser($request->user())->allows('viewTelescope');
        });
    }
}
