<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends push notifications through Expo's push API.
 * Tokens that Expo reports as DeviceNotRegistered are cleared.
 */
class ExpoPushService
{
    private const CHUNK = 100; // Expo's per-request limit

    /**
     * @param  Collection<int, Device>  $devices
     * @return array{sent: int, failed: int, pruned: int}
     */
    public function send(Collection $devices, string $title, string $body, array $data = []): array
    {
        $stats = ['sent' => 0, 'failed' => 0, 'pruned' => 0];
        $devices = $devices->filter(fn (Device $d) => filled($d->expo_push_token))->values();

        foreach ($devices->chunk(self::CHUNK) as $chunk) {
            $chunk = $chunk->values();
            $messages = $chunk->map(fn (Device $device) => [
                'to' => $device->expo_push_token,
                'title' => $title,
                'body' => $body,
                'data' => (object) $data,
                'sound' => 'default',
                'channelId' => 'daily',
            ])->all();

            $response = Http::acceptJson()
                ->asJson()
                ->timeout(20)
                ->retry(2, 1000, throw: false)
                ->when(config('services.expo.access_token'), fn ($http, $token) => $http->withToken($token))
                ->post(config('services.expo.push_url'), $messages);

            if ($response->failed()) {
                $stats['failed'] += count($messages);
                Log::warning('Expo push request failed', ['status' => $response->status(), 'body' => $response->body()]);

                continue;
            }

            foreach ($response->json('data', []) as $i => $ticket) {
                if (($ticket['status'] ?? null) === 'ok') {
                    $stats['sent']++;

                    continue;
                }

                $stats['failed']++;

                if (($ticket['details']['error'] ?? null) === 'DeviceNotRegistered' && isset($chunk[$i])) {
                    $chunk[$i]->update(['expo_push_token' => null]);
                    $stats['pruned']++;
                }
            }
        }

        return $stats;
    }
}
