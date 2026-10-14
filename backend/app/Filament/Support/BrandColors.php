<?php

namespace App\Filament\Support;

/**
 * Admin palettes built around the app design tokens (600 = the exact design colour),
 * so buttons, badges and links use the same greens, terracotta and amber as the app.
 */
class BrandColors
{
    public const PRIMARY = [50 => '#f3f7f6', 100 => '#e2ebea', 200 => '#c3d6d2', 300 => '#9abbb4', 400 => '#6a9a91', 500 => '#3a796d', 600 => '#0F5C4D', 700 => '#0A4338', 800 => '#093930', 900 => '#072c25', 950 => '#051d19'];

    public const DANGER = [50 => '#fbf5f4', 100 => '#f6e8e5', 200 => '#eccfca', 300 => '#dfafa6', 400 => '#d0897b', 500 => '#c16250', 600 => '#B3402A', 700 => '#8f3322', 800 => '#6f281a', 900 => '#561f14', 950 => '#39140d'];

    public const SUCCESS = [50 => '#f5faf8', 100 => '#e6f3ee', 200 => '#cbe6db', 300 => '#a7d5c3', 400 => '#7dc1a6', 500 => '#54ad89', 600 => '#2E9B6F', 700 => '#257c59', 800 => '#1d6045', 900 => '#164a35', 950 => '#0f3224'];

    /** Accent #F2A93B sits at 400; 600 is dark enough for white text. */
    public const WARNING = [50 => '#fefaf2', 100 => '#fdf1dc', 200 => '#fae2b8', 300 => '#f6cc85', 400 => '#F2A93B', 500 => '#d9922a', 600 => '#C98419', 700 => '#a16a14', 800 => '#7d5210', 900 => '#603f0c', 950 => '#402a08'];

    /** Warm greys from the design (bg #FBF7F1, border #E6DDCF, muted #6B645B, ink #1C1A17). */
    public const GRAY = [50 => '#FBF7F1', 100 => '#F4EEE5', 200 => '#E6DDCF', 300 => '#D3C8B8', 400 => '#A39A8D', 500 => '#6B645B', 600 => '#57514A', 700 => '#433E38', 800 => '#2E2A26', 900 => '#1C1A17', 950 => '#12100E'];
}
