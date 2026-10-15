<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The web version of the app (built with `npm run build:web` into public/app).
 * Its files are served directly by the web server; every app route (/app, /app/today,
 * /app/setup/family …) gets the same page (public/app/shell.html) and the app's router takes over.
 */
class WebAppController extends Controller
{
    public function __invoke(Request $request, ?string $path = null): Response
    {
        // A missing file (old build hash, typo) is a real 404, not the app shell.
        if ($path !== null && preg_match('/\.[A-Za-z0-9]{1,8}$/', $path)) {
            abort(404);
        }

        $index = public_path('app/shell.html');
        if (! is_file($index)) {
            abort(503, 'The web app has not been built yet. Run `npm run build:web` in frontend/.');
        }

        return response((string) file_get_contents($index), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            // Always fetch the shell fresh so a new deploy is picked up; the hashed bundles it
            // points to are cached for a year by the web server.
            'Cache-Control' => 'no-cache, must-revalidate',
            'X-Robots-Tag' => 'noindex',
            'Content-Security-Policy' => implode('; ', array_filter([
                "default-src 'self'",
                "script-src 'self' 'wasm-unsafe-eval'",
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data: blob: https:",
                "media-src 'self' blob:",
                "font-src 'self' data:",
                "connect-src 'self'",
                "worker-src 'self' blob:",
                "frame-ancestors 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "object-src 'none'",
                $request->isSecure() ? 'upgrade-insecure-requests' : null,
            ])),
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            // The app asks for location (weather) and the camera (dish photos) itself.
            'Permissions-Policy' => 'camera=(self), microphone=(), geolocation=(self), interest-cohort=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ]);
    }
}
