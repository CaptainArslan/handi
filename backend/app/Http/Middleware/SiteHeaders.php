<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security and caching headers for the public website. The site ships no JavaScript,
 * so the content security policy blocks every script.
 */
class SiteHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Content-Security-Policy', implode('; ', array_filter([
            "default-src 'self'",
            "script-src 'none'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https:",
            "font-src 'self'",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            // Only over HTTPS: on a plain-http local domain it would rewrite every image and font
            // to https:// and they would fail to load.
            $request->isSecure() ? 'upgrade-insecure-requests' : null,
        ])));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), interest-cohort=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Pages carry no cookies or personal data, so browsers and proxies may keep them for a few minutes.
        if ($response->isSuccessful() && $request->isMethodCacheable()) {
            $response->headers->set('Cache-Control', 'public, max-age=300, stale-while-revalidate=600');
        }

        return $response;
    }
}
