<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PartnerResource;
use App\Models\Event;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

/**
 * Unified exhibitor | sponsor management (architecture §6.3). Organizer-side.
 */
class PartnerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Partner::with('package')->withCount('members')->latest('id');

        if ($request->filled('event')) {
            $event = Event::where('uuid', $request->string('event'))->firstOrFail();
            $query->where('event_id', $event->id);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        return PartnerResource::collection($query->get());
    }

    public function show(string $uuid): JsonResponse
    {
        $partner = Partner::with(['package', 'members.contact', 'products'])->where('uuid', $uuid)->firstOrFail();

        return response()->json(['data' => new PartnerResource($partner)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event' => ['required', 'string'],
            'type' => ['required', 'in:exhibitor,sponsor'],
            'name' => ['required', 'string', 'max:180'],
            'package_id' => ['nullable', 'integer', 'exists:partner_packages,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'website' => ['nullable', 'string', 'max:255'],
            'tier_rank' => ['nullable', 'integer'],
            'profile' => ['nullable', 'array'],
        ]);

        $event = Event::where('uuid', $data['event'])->firstOrFail();

        $partner = Partner::create([
            'event_id' => $event->id,
            'type' => $data['type'],
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name'], $event->id),
            'package_id' => $data['package_id'] ?? null,
            'description' => $data['description'] ?? null,
            'website' => $data['website'] ?? null,
            'tier_rank' => $data['tier_rank'] ?? 0,
            'profile_data' => $data['profile'] ?? null,
            'status' => 'active',
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => new PartnerResource($partner->load('package'))], 201);
    }

    public function update(string $uuid, Request $request): JsonResponse
    {
        $partner = Partner::where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:1000'],
            'website' => ['nullable', 'string', 'max:255'],
            'tier_rank' => ['nullable', 'integer'],
            'status' => ['sometimes', 'in:draft,active,suspended'],
            'profile' => ['nullable', 'array'],
        ]);

        if (array_key_exists('profile', $data)) {
            $data['profile_data'] = $data['profile'];
            unset($data['profile']);
        }

        $partner->update($data + ['updated_by' => $request->user()->id]);

        return response()->json(['data' => new PartnerResource($partner->fresh()->load('package'))]);
    }

    protected function uniqueSlug(string $name, int $eventId): string
    {
        $base = Str::slug($name) ?: 'partner';
        $slug = $base;
        $i = 1;
        while (Partner::where('event_id', $eventId)->where('slug', $slug)->withTrashed()->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
