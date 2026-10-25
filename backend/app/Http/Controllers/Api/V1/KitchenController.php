<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kitchen\NotificationSettingsRequest;
use App\Http\Requests\Kitchen\RegisterDeviceRequest;
use App\Http\Requests\Kitchen\SetLocationRequest;
use App\Http\Requests\Kitchen\UpdateKitchenRequest;
use App\Http\Resources\KitchenResource;
use App\Models\Device;
use App\Models\User;
use App\Services\ExpoPushService;
use App\Services\KitchenService;
use App\Services\PickService;
use App\Services\WeatherService;
use App\Support\Activity;
use App\Support\Tokens;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

#[Group('Kitchen settings', weight: 4)]
class KitchenController extends Controller
{
    /**
     * Update kitchen
     *
     * Send any of the fields. Changes apply from tomorrow's picks.
     */
    public function update(UpdateKitchenRequest $request): KitchenResource
    {
        $data = $request->validated();
        $kitchen = $request->user()->kitchenOrDefault();

        if (isset($data['meals'])) {
            $data['meals'] = array_values($data['meals']);
        }

        $kitchen->update($data);
        Activity::log($request->user(), 'kitchen.updated', 'Changed kitchen settings', $kitchen, $data);

        return new KitchenResource($kitchen->load('city.province'));
    }

    /**
     * Set location
     *
     * `{mode:"city", city_id}` or `{mode:"gps", lat, lng}` (rounded to ~1 km). Returns the kitchen and today's weather there.
     */
    public function location(SetLocationRequest $request, KitchenService $kitchens, WeatherService $weather): JsonResponse
    {
        $data = $request->validated();

        $kitchen = $kitchens->applyLocation($request->user()->kitchenOrDefault(), $data);
        Activity::log($request->user(), 'kitchen.location_changed', 'Changed location to '.$kitchen->location_label, $kitchen, ['mode' => $data['mode']]);

        return response()->json(['data' => [
            'kitchen' => (new KitchenResource($kitchen))->resolve($request),
            'weather' => $weather->forKitchen($kitchen),
        ]]);
    }

    /**
     * Notification settings
     */
    #[Group('Notifications', weight: 9)]
    public function notificationSettings(Request $request): JsonResponse
    {
        $kitchen = $request->user()->kitchenOrDefault();

        return response()->json(['data' => ['enabled' => $kitchen->notifications_enabled, 'time' => $kitchen->notify_time]]);
    }

    /**
     * Update notification settings
     *
     * `time` is "HH:mm" between 05:00 and 13:00.
     */
    #[Group('Notifications', weight: 9)]
    public function updateNotificationSettings(NotificationSettingsRequest $request): JsonResponse
    {
        $data = $request->validated();

        $kitchen = $request->user()->kitchenOrDefault();
        $kitchen->update(['notifications_enabled' => $data['enabled'], 'notify_time' => $data['time']]);
        Activity::log($request->user(), 'notifications.settings_updated', ($data['enabled'] ? 'Reminder on at ' : 'Reminder off (').$data['time'].($data['enabled'] ? '' : ')'));

        return response()->json(['data' => ['enabled' => $kitchen->notifications_enabled, 'time' => $kitchen->notify_time]]);
    }

    /**
     * Send a test notification
     */
    #[Group('Notifications', weight: 9)]
    public function testNotification(Request $request, ExpoPushService $push): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $picks = app(PickService::class)->today($user);
        $names = $user->kitchenDishes()->withDetails()->whereIn('id', $picks->dishIds())->get()->map->effectiveName()->join(', ');

        $stats = $push->send($user->devices()->get(), config('handi.app_name'), 'Today’s picks: '.$names, ['link' => '/today']);

        return response()->json(['data' => ['sent' => $stats['sent'], 'devices' => $user->devices()->whereNotNull('expo_push_token')->count()]], 202);
    }

    /**
     * Register this phone for push
     *
     * Upserts by `device_id` and links it to this login, so logging out stops reminders on this phone.
     */
    #[Group('Notifications', weight: 9)]
    public function registerDevice(RegisterDeviceRequest $request): JsonResponse
    {
        $data = $request->validated();

        // One phone belongs to whoever is signed in on it now (a shared family phone can switch accounts).
        // Its push token is removed from every other row, so a phone never gets another account's reminders.
        if (! empty($data['expo_push_token'])) {
            Device::where('expo_push_token', $data['expo_push_token'])->where('device_id', '!=', $data['device_id'])->update(['expo_push_token' => null]);
        }

        $device = Device::updateOrCreate(['device_id' => $data['device_id']], [
            'user_id' => $request->user()->id,
            'personal_access_token_id' => Tokens::currentId($request->user()),
            'expo_push_token' => $data['expo_push_token'] ?? null,
            'platform' => $data['platform'],
            'name' => $data['name'] ?? null,
            'last_seen_at' => now(),
        ]);

        return response()->json(['data' => ['registered' => true, 'id' => $device->id]], $device->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Remove a phone
     */
    #[Group('Notifications', weight: 9)]
    public function removeDevice(Request $request, string $deviceId): Response
    {
        $request->user()->devices()->where('device_id', $deviceId)->delete();

        return response()->noContent();
    }
}
