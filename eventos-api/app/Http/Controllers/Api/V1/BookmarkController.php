<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Participation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Per-attendee "save" bookmarks across the event app — speakers, sessions,
 * delegates and exhibitors. Stored on the caller's own participation row
 * (meta.bookmarks.{type} = [ids]) so saves follow the account across devices.
 * Acts as the resolved participation (ResolveParticipant middleware).
 */
class BookmarkController extends Controller
{
    public const TYPES = ['speaker', 'session', 'delegate', 'exhibitor'];

    /** GET /events/{event}/bookmarks — all saved ids, grouped by type. */
    public function index(Request $request): JsonResponse
    {
        $participation = Participation::on('pgsql_admin')
            ->findOrFail($request->attributes->get('participation_id'));

        $saved = $participation->meta['bookmarks'] ?? [];

        return response()->json([
            'data' => collect(self::TYPES)
                ->mapWithKeys(fn (string $t) => [$t => array_values($saved[$t] ?? [])]),
        ]);
    }

    /** POST /events/{event}/bookmarks {type, id, on} — set one bookmark. */
    public function toggle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', self::TYPES)],
            'id' => ['required', 'string', 'max:64'],
            'on' => ['required', 'boolean'],
        ]);

        $participation = Participation::on('pgsql_admin')
            ->findOrFail($request->attributes->get('participation_id'));

        $meta = $participation->meta ?? [];
        $list = collect($meta['bookmarks'][$data['type']] ?? []);
        $list = $data['on']
            ? $list->push($data['id'])->unique()->values()
            : $list->reject(fn ($id) => $id === $data['id'])->values();

        // Keep the meta blob bounded.
        abort_if($list->count() > 500, 422, 'Bookmark limit reached.');

        $meta['bookmarks'][$data['type']] = $list->all();
        $participation->meta = $meta;
        $participation->save();

        return response()->json([
            'data' => ['type' => $data['type'], 'id' => $data['id'], 'on' => (bool) $data['on']],
        ]);
    }
}
