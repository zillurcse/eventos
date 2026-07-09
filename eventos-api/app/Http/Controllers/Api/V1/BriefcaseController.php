<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Participation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The attendee "Briefcase" — a personal collection of files (exhibitor
 * brochures, session docs, …) saved from around the event app. Stored on the
 * caller's own participation row (meta.briefcase = [{id,title,url,kind}]) so it
 * follows the account across devices. Acts as the resolved participation
 * (ResolveParticipant middleware).
 */
class BriefcaseController extends Controller
{
    private const LIMIT = 200;

    /** GET /events/{event}/briefcase — every saved file. */
    public function index(Request $request): JsonResponse
    {
        $participation = Participation::on('pgsql_admin')
            ->findOrFail($request->attributes->get('participation_id'));

        return response()->json(['data' => array_values($participation->meta['briefcase'] ?? [])]);
    }

    /** POST /events/{event}/briefcase {title, url, kind} — add a file (deduped by url). */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'url' => ['required', 'string', 'max:1000'],
            'kind' => ['nullable', 'string', 'max:20'],
        ]);

        $participation = Participation::on('pgsql_admin')
            ->findOrFail($request->attributes->get('participation_id'));

        $meta = $participation->meta ?? [];
        $items = collect($meta['briefcase'] ?? []);

        $existing = $items->firstWhere('url', $data['url']);
        if ($existing) {
            return response()->json(['data' => $existing]);
        }

        abort_if($items->count() >= self::LIMIT, 422, 'Your briefcase is full.');

        $item = [
            'id' => (string) Str::uuid(),
            'title' => $data['title'],
            'url' => $data['url'],
            'kind' => $data['kind'] ?? 'file',
        ];

        $meta['briefcase'] = $items->push($item)->all();
        $participation->meta = $meta;
        $participation->save();

        return response()->json(['data' => $item], 201);
    }

    /** DELETE /events/{event}/briefcase/{item} — remove one saved file. */
    public function destroy(Request $request, string $item): JsonResponse
    {
        $participation = Participation::on('pgsql_admin')
            ->findOrFail($request->attributes->get('participation_id'));

        $meta = $participation->meta ?? [];
        $meta['briefcase'] = collect($meta['briefcase'] ?? [])
            ->reject(fn ($x) => ($x['id'] ?? null) === $item)
            ->values()->all();

        $participation->meta = $meta;
        $participation->save();

        return response()->json(['message' => 'Removed from briefcase.']);
    }
}
