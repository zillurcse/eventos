<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Participation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Per-attendee notes jotted against a speaker, session, or delegate from
 * their respective cards. Stored on the caller's own participation row
 * (meta.notes.{type} = [{id, target_id, text, created_at, updated_at}]),
 * one note per target, so it follows the account across devices and is
 * browsable later from Profile › My Briefcase › Notes.
 */
class NoteController extends Controller
{
    public const TYPES = ['speaker', 'session', 'delegate'];

    /** GET /events/{event}/notes — all notes, grouped by type. */
    public function index(Request $request): JsonResponse
    {
        $participation = Participation::on('pgsql_admin')
            ->findOrFail($request->attributes->get('participation_id'));

        $notes = $participation->meta['notes'] ?? [];

        return response()->json([
            'data' => collect(self::TYPES)
                ->mapWithKeys(fn (string $t) => [$t => array_values($notes[$t] ?? [])]),
        ]);
    }

    /** POST /events/{event}/notes {type, target_id, text} — create or update the note for a target. */
    public function save(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', self::TYPES)],
            'target_id' => ['required', 'string', 'max:64'],
            'text' => ['required', 'string', 'max:2000'],
        ]);

        $participation = Participation::on('pgsql_admin')
            ->findOrFail($request->attributes->get('participation_id'));

        $meta = $participation->meta ?? [];
        $list = collect($meta['notes'][$data['type']] ?? []);
        $existing = $list->firstWhere('target_id', $data['target_id']);

        $note = [
            'id' => $existing['id'] ?? (string) Str::uuid(),
            'target_id' => $data['target_id'],
            'text' => $data['text'],
            'created_at' => $existing['created_at'] ?? now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        $list = $list->reject(fn ($n) => $n['target_id'] === $data['target_id'])->push($note)->values();

        // Keep the meta blob bounded.
        abort_if($list->count() > 500, 422, 'Note limit reached.');

        $meta['notes'][$data['type']] = $list->all();
        $participation->meta = $meta;
        $participation->save();

        return response()->json(['data' => $note]);
    }

    /** DELETE /events/{event}/notes/{type}/{targetId} — remove the note for a target. */
    public function destroy(Request $request, string $type, string $targetId): JsonResponse
    {
        abort_unless(in_array($type, self::TYPES, true), 404);

        $participation = Participation::on('pgsql_admin')
            ->findOrFail($request->attributes->get('participation_id'));

        $meta = $participation->meta ?? [];
        $list = collect($meta['notes'][$type] ?? []);
        $meta['notes'][$type] = $list->reject(fn ($n) => $n['target_id'] === $targetId)->values()->all();
        $participation->meta = $meta;
        $participation->save();

        return response()->json(['data' => ['ok' => true]]);
    }
}
