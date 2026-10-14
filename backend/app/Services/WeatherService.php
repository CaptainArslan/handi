<?php

namespace App\Services;

use App\Models\City;
use App\Models\Kitchen;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Today's weather from Open-Meteo (no API key), cached per ~1 km cell.
 * Conditions are bucketed into the four the app shows: hot, mild, cold, rainy.
 */
class WeatherService
{
    public const HEADLINES = [
        'hot' => 'It’s hot today. How about something light?',
        'mild' => 'Pleasant weather today. Anything goes.',
        'cold' => 'It’s chilly today. Something warm and hearty?',
        'rainy' => 'Rainy day today. Pakoras weather.',
    ];

    /** Weather for a kitchen's location, or null when it has none. */
    public function forKitchen(Kitchen $kitchen): ?array
    {
        $coords = $kitchen->coordinates();

        if (! $coords) {
            return null;
        }

        return $this->forCoordinates($coords[0], $coords[1], $kitchen->cityName());
    }

    public function forCity(City $city): array
    {
        return $this->forCoordinates($city->lat, $city->lng, $city->name);
    }

    public function forCoordinates(float $lat, float $lng, ?string $label = null): array
    {
        $lat = round($lat, 2);
        $lng = round($lng, 2);
        $key = "weather:{$lat},{$lng}";

        $data = Cache::get($key);

        if (! $data) {
            $data = $this->fetch($lat, $lng);

            if ($data) {
                Cache::put($key, $data, now()->addMinutes((int) config('handi.weather.cache_minutes', 60)));
            }
        }

        $data ??= ['temp_now' => null, 'temp_high' => null, 'rain_chance' => null, 'condition' => 'mild', 'available' => false, 'fetched_at' => now()->toIso8601String()];
        $data['city_label'] = $label;
        $data['summary'] = $this->summary($data);
        $data['headline'] = self::HEADLINES[$data['condition']];

        return $data;
    }

    private function fetch(float $lat, float $lng): ?array
    {
        try {
            $response = Http::timeout(6)->acceptJson()->get(config('handi.weather.url'), [
                'latitude' => $lat,
                'longitude' => $lng,
                'current' => 'temperature_2m,weather_code',
                'daily' => 'temperature_2m_max,precipitation_probability_max',
                'forecast_days' => 1,
                'timezone' => 'auto',
            ]);

            if ($response->failed()) {
                return null;
            }

            $temp = $response->json('current.temperature_2m');
            $code = (int) $response->json('current.weather_code', 0);
            $high = $response->json('daily.temperature_2m_max.0');
            $rain = $response->json('daily.precipitation_probability_max.0');

            if ($temp === null) {
                return null;
            }

            return [
                'temp_now' => (int) round($temp),
                'temp_high' => $high !== null ? (int) round($high) : null,
                'rain_chance' => $rain !== null ? (int) $rain : null,
                'condition' => self::condition((float) $temp, $code, $rain !== null ? (int) $rain : 0),
                'available' => true,
                'fetched_at' => now()->toIso8601String(),
            ];
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Bucket raw weather into hot | mild | cold | rainy. */
    public static function condition(float $temp, int $weatherCode, int $rainChance): string
    {
        // WMO codes: 51–67 drizzle/rain, 80–82 showers, 95–99 thunderstorm.
        $raining = ($weatherCode >= 51 && $weatherCode <= 67) || ($weatherCode >= 80 && $weatherCode <= 82) || $weatherCode >= 95;

        if ($raining || $rainChance >= 60) {
            return 'rainy';
        }

        if ($temp >= 30) {
            return 'hot';
        }

        if ($temp <= 16) {
            return 'cold';
        }

        return 'mild';
    }

    /** "31° · hot · rain 10%" */
    private function summary(array $data): string
    {
        if ($data['temp_now'] === null) {
            return 'Weather unavailable';
        }

        $parts = [$data['temp_now'].'°', $data['condition']];
        $parts[] = $data['rain_chance'] ? 'rain '.$data['rain_chance'].'%' : 'dry';

        return implode(' · ', $parts);
    }
}
