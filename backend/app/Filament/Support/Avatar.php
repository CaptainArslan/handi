<?php

namespace App\Filament\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Illuminate\Database\Eloquent\Model;

/**
 * Initials avatars drawn locally as SVG (no third-party avatar service): amber circle,
 * dark initial — the same look as the app's avatar.
 */
class Avatar implements AvatarProvider
{
    private const PALETTE = [['#F2A93B', '#1C1A17'], ['#0F5C4D', '#FFFFFF'], ['#E3F0EB', '#0A4338'], ['#FDF0D9', '#8A5A0B'], ['#E2EEF7', '#2C5F8A'], ['#FBE6E0', '#9A3A22']];

    public function get(Model $record): string
    {
        return self::for((string) ($record->getAttribute('name') ?? $record->getAttribute('email') ?? '?'));
    }

    public static function for(string $name, ?int $seed = null): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: ['?'];
        $initials = mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1).(count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
        [$bg, $fg] = self::PALETTE[($seed ?? crc32($name)) % count(self::PALETTE)];

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" viewBox="0 0 96 96">'
            .'<rect width="96" height="96" rx="48" fill="'.$bg.'"/>'
            .'<text x="48" y="50" dominant-baseline="middle" text-anchor="middle" font-family="Poppins, Arial, sans-serif" font-size="38" font-weight="700" fill="'.$fg.'">'
            .htmlspecialchars($initials, ENT_XML1).'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
