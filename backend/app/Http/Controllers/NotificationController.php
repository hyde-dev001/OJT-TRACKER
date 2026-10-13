<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $notifications = $user->notifications()->latest()->paginate(10);

        return response()->json([
            'data' => $notifications->getCollection()
                ->map(fn (DatabaseNotification $notification): array => $this->toArray($notification))
                ->values(),
            'links' => [
                'first' => $notifications->url(1),
                'last' => $notifications->url($notifications->lastPage()),
                'prev' => $notifications->previousPageUrl(),
                'next' => $notifications->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'from' => $notifications->firstItem(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'to' => $notifications->lastItem(),
                'total' => $notifications->total(),
            ],
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $record = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $record->markAsRead();

        return response()->json(['data' => $this->toArray($record->fresh())]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([], 204);
    }

    /** @return array<string, mixed> */
    private function toArray(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'event' => $data['event'] ?? null,
            'title' => $data['title'] ?? null,
            'message' => $data['message'] ?? null,
            'item_type' => $data['item_type'] ?? null,
            'item_id' => $data['item_id'] ?? null,
            'intern_id' => $data['intern_id'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'destination' => $data['destination'] ?? null,
            'read_at' => $notification->read_at?->toISOString(),
            'created_at' => $notification->created_at?->toISOString(),
        ];
    }
}
