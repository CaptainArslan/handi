<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/** The website's stylesheet, minified and inlined into each page (one request for first paint). */
class SiteAssets
{
    public static function css(): string
    {
        $path = public_path('css/handi-site.css');

        return Cache::rememberForever('site:css:v2:'.filemtime($path), function () use ($path) {
            $css = (string) file_get_contents($path);
            $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);          // comments
            $css = (string) preg_replace('/\s+/', ' ', $css);                 // whitespace runs
            $css = (string) preg_replace('/\s*([{};:,>])\s*/', '$1', $css);   // around punctuation
            $css = str_replace(';}', '}', $css);

            // Root-relative, so the cached copy works on any host or scheme (www, http → https, local).
            $fonts = rtrim((string) parse_url(asset('fonts'), PHP_URL_PATH), '/');

            return str_replace("url('../fonts/", "url('".$fonts.'/', trim($css));
        });
    }
}
