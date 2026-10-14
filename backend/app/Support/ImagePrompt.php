<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The "make a photo with AI" prompt shown under "Add a photo" in the app.
 *
 * Users copy it (with their dish name filled in), paste it into Gemini, and
 * upload the result, so every dish photo keeps the same illustration style.
 * Admins edit it in Filament (Dish library → AI photo prompt).
 */
class ImagePrompt
{
    public const KEY = 'image_prompt';

    /** Replaced with the dish name in the app. */
    public const PLACEHOLDER = '{{dish_name}}';

    public const DEFAULT_AI_URL = 'https://gemini.google.com/app';

    /**
     * Current settings, falling back to defaults for anything not saved yet.
     *
     * @return array{enabled: bool, intro: string, template: string, ai_url: string, ai_name: string}
     */
    public static function get(): array
    {
        $saved = Setting::read(self::KEY) ?? [];

        return [
            'enabled' => (bool) ($saved['enabled'] ?? true),
            'intro' => (string) (($saved['intro'] ?? null) ?: self::defaultIntro()),
            'template' => (string) (($saved['template'] ?? null) ?: self::defaultTemplate()),
            'ai_url' => (string) (($saved['ai_url'] ?? null) ?: self::DEFAULT_AI_URL),
            'ai_name' => (string) (($saved['ai_name'] ?? null) ?: 'Gemini'),
        ];
    }

    /** @param  array{enabled: bool, intro: string, template: string, ai_url: string, ai_name: string}  $data */
    public static function save(array $data): void
    {
        Setting::write(self::KEY, [
            'enabled' => (bool) $data['enabled'],
            'intro' => trim($data['intro']),
            'template' => self::pretty($data['template']),
            'ai_url' => trim($data['ai_url']),
            'ai_name' => trim($data['ai_name']),
        ]);
    }

    public static function reset(): void
    {
        Setting::write(self::KEY, null);
    }

    /** The full text a user copies: intro + JSON, with the dish name filled in. */
    public static function render(string $dishName, ?array $settings = null): string
    {
        $s = $settings ?? self::get();
        $jsonSafe = substr(json_encode($dishName, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 1, -1);

        return str_replace(self::PLACEHOLDER, $dishName, $s['intro'])
            ."\n\n"
            .str_replace(self::PLACEHOLDER, $jsonSafe, $s['template']);
    }

    /** Null when the template is usable, otherwise a message for the admin form. */
    public static function templateError(?string $template): ?string
    {
        if (blank($template)) {
            return 'The JSON template is required.';
        }
        if (! str_contains($template, self::PLACEHOLDER)) {
            return 'Add '.self::PLACEHOLDER.' where the dish name should go.';
        }
        json_decode(str_replace(self::PLACEHOLDER, 'Sample dish', $template));
        if (json_last_error() !== JSON_ERROR_NONE) {
            return 'This is not valid JSON: '.json_last_error_msg().'.';
        }

        return null;
    }

    /** Re-indent valid JSON so the app and the admin show it the same way. */
    public static function pretty(string $template): string
    {
        $decoded = json_decode($template, true);

        return is_array($decoded)
            ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : trim($template);
    }

    public static function defaultIntro(): string
    {
        return 'Create one square image of '.self::PLACEHOLDER.' that follows this JSON illustration spec exactly. Keep the same style, lighting, angle and white background. Reply with the image only.';
    }

    public static function defaultTemplate(): string
    {
        $d = self::PLACEHOLDER;

        return self::pretty(json_encode([
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'name' => 'FoodIllustrationSpecification',
            'description' => 'Standard schema for generating consistent culinary UI/app illustrations',
            'metadata' => [
                'dish_name' => $d,
                'cuisine' => 'South Asian (Pakistani home cooking)',
                'view_angle' => "Top-down bird's-eye view with slight 15-degree dynamic tilt",
                'art_style' => [
                    'genre' => 'Stylized 2D digital food art / Vector-meets-painterly',
                    'lighting' => 'Warm ambient top light with soft gloss highlights and subtle cast shadows',
                    'texture' => "Glossy gravy, glaze or grains true to {$d}, visible oil separation (roghan) where traditional, coarse spice specks",
                    'outline' => 'Medium-weight clean line art with soft dark-toned borders',
                ],
            ],
            'canvas' => [
                'aspect_ratio' => '1:1',
                'background' => ['type' => 'Isolated solid white background', 'pattern' => 'None', 'vignette' => 'None'],
            ],
            'cookware' => [
                'type' => "The traditional serving vessel for {$d} (karahi, handi, degchi, bowl or plate)",
                'shape' => 'Round, centred, seen from above',
                'material' => 'Seasoned dark cast iron, copper or ceramic — whichever is authentic to the dish',
                'colors' => ['rim_primary' => '#2B2A29', 'rim_shadow' => '#171615', 'rim_highlight' => '#4A4846'],
                'fx' => ['steam' => ['visible' => true, 'style' => 'Subtle translucent white wisps rising from center']],
            ],
            'base' => [
                'type' => "The authentic base of {$d} (gravy, rice, lentils or dry masala)",
                'consistency' => "Rich and glistening, exactly how {$d} is served at home",
                'color_palette' => 'Warm natural tones true to the dish; rich saturation, never neon',
            ],
            'main_ingredients' => [
                'item' => "The signature ingredients of {$d}",
                'count_approx' => '5-6 clearly readable pieces',
                'rendering' => [
                    'texture' => 'Tender, cooked surface with browned sear marks and glaze clinging tightly',
                    'highlights' => 'Soft warm gloss on the top edges',
                ],
            ],
            'garnishes' => [
                'rule' => "Only garnishes that are traditional for {$d}",
                'examples' => ['Julienned ginger', 'Slit green chilies', 'Fresh coriander sprigs', 'Lemon wedge', 'Crispy fried onions'],
            ],
            'consistency_rules' => [
                'One dish only, centred, filling about 80% of the canvas',
                'No text, labels, logos, hands, cutlery or table props',
                'Same lighting, outline weight and tilt as every other Handi dish illustration',
                'Pure white background so the app can place it on any colour',
            ],
            'output' => ['format' => 'PNG', 'size' => '1024x1024 px'],
            'prompt_template' => "A rich, vibrant digital vector illustration of {$d} in its traditional serving vessel, top-down bird's-eye perspective with a slight 15-degree tilt, warm cozy lighting, bold clean outlines, stylized food art for a mobile app menu, isolated solid white background, authentic ingredients and garnishes of {$d}, highly detailed, professional UI asset.",
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
