<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\ListNotificationsRequest;
use App\Http\Requests\Notification\MarkNotificationRequest;
use App\Http\Resources\NotificationResource;
use App\Models\AppNotification;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Notification center.
 */
#[Group('Notifications', weight: 9)]
class NotificationController extends Controller
{
    /**
     * Notifications
     *
     * Newest first. `filter` = all | unread.
     */
    public function index(ListNotificationsRequest $request): JsonResponse
    {
        $data = $request->validated();

        $items = $request->user()->appNotifications()
            ->when(($data['filter'] ?? 'all') === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->latest('id')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => NotificationResource::collection($items)->resolve($request),
            'meta' => ['unread_count' => $request->user()->appNotifications()->whereNull('read_at')->count()],
        ]);
    }

    /**
     * Mark as read
     */
    public function update(MarkNotificationRequest $request, int $id): NotificationResource
    {
        $item = $this->find($request, $id);
        $item->update(['read_at' => $request->boolean('read') ? ($item->read_at ?? now()) : null]);

        return new NotificationResource($item);
    }

    /**
     * Mark all as read
     */
    public function readAll(Request $request): Response
    {
        $request->user()->appNotifications()->whereNull('read_at')->update(['read_at' => now()]);

        return response()->noContent();
    }

    /**
     * Clear
     *
     * Undo with restore.
     */
    public function destroy(Request $request, int $id): Response
    {
        $this->find($request, $id)->delete();

        return response()->noContent();
    }

    /**
     * Undo clear
     */
    public function restore(Request $request, int $id): NotificationResource
    {
        $item = AppNotification::onlyTrashed()->where('user_id', $request->user()->id)->findOrFail($id);
        $item->restore();

        return new NotificationResource($item);
    }

    private function find(Request $request, int $id): AppNotification
    {
        return AppNotification::where('user_id', $request->user()->id)->findOrFail($id);
    }
}
