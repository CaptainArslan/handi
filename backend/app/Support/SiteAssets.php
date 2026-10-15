<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/** The website's stylesheet, minified and inlined into each page (one request for first paint). */
class SiteAssets
{
    public static function css(): string
    {
        $path = public_path('css/handi-site.css');

        return Cache::rememberForever('site:css:'.filemtime($path), function () use ($path) {
            $css = (string) file_get_contents($path);
            $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);          // comments
            $css = (string) preg_replace('/\s+/', ' ', $css);                 // whitespace runs
            $css = (string) preg_replace('/\s*([{};:,>])\s*/', '$1', $css);   // around punctuation
            $css = str_replace(';}', '}', $css);

            return str_replace("url('../fonts/", "url('".asset('fonts').'/', trim($css));
        });
    }
}
