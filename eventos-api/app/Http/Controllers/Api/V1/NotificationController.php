<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Notification;
use App\Models\Participation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * In-app notifications for the signed-in user (architecture §6.7). Identity-plane
 * reads (across orgs) run on the migrator connection.
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $this->mine($request)
            ->where('channel', 'in_app')
            ->latest('id')
            ->limit(50)
            ->get();

        return response()->json([
            'unread' => $notifications->whereNull('read_at')->count(),
            'data' => $notifications->map(fn (Notification $n) => [
                'id' => $n->uuid,
                'title' => $n->title,
                'body' => $n->body,
                'status' => $n->status,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function markRead(string $uuid, Request $request): JsonResponse
    {
        $notification = $this->mine($request)->where('uuid', $uuid)->firstOrFail();
        $notification->update(['read_at' => now(), 'status' => 'read']);

        return response()->json(['data' => ['id' => $notification->uuid, 'status' => 'read']]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $count = $this->mine($request)->whereNull('read_at')->update(['read_at' => now(), 'status' => 'read']);

        return response()->json(['marked_read' => $count]);
    }

    /** Notifications addressed to this user, their contacts, or participations. */
    protected function mine(Request $request): Builder
    {
        $user = $request->user();
        $contactIds = Contact::on('pgsql_admin')->where('user_id', $user->id)->pluck('id');
        $participationIds = Participation::on('pgsql_admin')->whereIn('contact_id', $contactIds)->pluck('id');

        return Notification::on('pgsql_admin')->where(function (Builder $q) use ($user, $contactIds, $participationIds) {
            $q->where(fn (Builder $x) => $x->where('notifiable_type', 'user')->where('notifiable_id', $user->id))
                ->orWhere(fn (Builder $x) => $x->where('notifiable_type', 'participation')->whereIn('notifiable_id', $participationIds))
                ->orWhere(fn (Builder $x) => $x->where('notifiable_type', 'contact')->whereIn('notifiable_id', $contactIds));
        });
    }
}
