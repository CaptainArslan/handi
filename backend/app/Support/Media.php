<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Media
{
    /** Bundled dish art shipped in public/images/dishes/{key}.png */
    public const ART_KEYS = ['lentils', 'bhindi', 'karahi', 'biryani', 'qeema', 'fish', 'paratha', 'karhi', 'aloogobi'];

    /** Background tint behind each art key (from the design). */
    public const ART_BG = [
        'lentils' => '#F7D98C', 'bhindi' => '#CFE6CF', 'karahi' => '#F6C2AE', 'biryani' => '#DCCFF0',
        'qeema' => '#EBCDB9', 'fish' => '#CDE2F2', 'paratha' => '#FFE3A6', 'karhi' => '#FFF0BF', 'aloogobi' => '#E4EBC4',
    ];

    /** Absolute URL for a stored file path, or null. Full URLs are returned unchanged. */
    public static function url(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        $url = Storage::disk(config('handi.media_disk'))->url($path);

        // A relative disk URL (/storage/...) becomes absolute on the host of the current request.
        return Str::startsWith($url, '/') ? url($url) : $url;
    }

    public static function artUrl(?string $key): ?string
    {
        return $key && in_array($key, self::ART_KEYS, true) ? asset('images/dishes/'.$key.'.png') : null;
    }

    public static function artBg(?string $key): string
    {
        return self::ART_BG[$key] ?? '#F1EBE1';
    }
}
