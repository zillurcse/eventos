<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Event;
use App\Models\Participation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpeakerController extends Controller
{
    public function index(string $uuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $speakers = Participation::with('contact')
            ->where('event_id', $event->id)
            ->speakers()
            ->orderBy('created_at')
            ->get()
            ->map(fn (Participation $p) => $this->format($p));

        return response()->json(['data' => $speakers]);
    }

    public function store(Request $request, string $uuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:250'],
            'email'       => ['required', 'email'],
            'designation' => ['nullable', 'string', 'max:250'],
            'company'     => ['nullable', 'string', 'max:250'],
            'bio'         => ['nullable', 'string'],
            'image_url'   => ['nullable', 'string', 'max:2000'],
            'facebook'    => ['nullable', 'string', 'max:500'],
            'linkedin'    => ['nullable', 'string', 'max:500'],
            'twitter'     => ['nullable', 'string', 'max:500'],
            'instagram'   => ['nullable', 'string', 'max:500'],
            'whatsapp'    => ['nullable', 'string', 'max:500'],
            'tags'        => ['nullable', 'array'],
            'tags.*'      => ['string', 'max:100'],
            'is_featured' => ['nullable', 'boolean'],
            'is_public'   => ['nullable', 'boolean'],
        ]);

        [$firstName, $lastName] = $this->splitName($data['name']);

        $contact = Contact::firstOrCreate(
            ['email' => $data['email']],
            ['first_name' => $firstName, 'last_name' => $lastName],
        );

        // Keep name fresh even if the contact already existed.
        $contact->update(['first_name' => $firstName, 'last_name' => $lastName]);

        $participation = Participation::firstOrCreate(
            ['event_id' => $event->id, 'contact_id' => $contact->id, 'role' => 'speaker'],
            ['status' => 'confirmed'],
        );

        $participation->update([
            'profile_data' => array_merge($participation->profile_data ?? [], [
                'designation' => $data['designation'] ?? '',
                'company'     => $data['company'] ?? '',
                'bio'         => $data['bio'] ?? '',
                'image_url'   => $data['image_url'] ?? null,
                'facebook'    => $data['facebook'] ?? '',
                'linkedin'    => $data['linkedin'] ?? '',
                'twitter'     => $data['twitter'] ?? '',
                'instagram'   => $data['instagram'] ?? '',
                'whatsapp'    => $data['whatsapp'] ?? '',
                'tags'        => $data['tags'] ?? [],
                'is_featured' => $data['is_featured'] ?? false,
                'is_public'   => $data['is_public'] ?? true,
            ]),
        ]);

        return response()->json(['data' => $this->format($participation->load('contact'))], 201);
    }

    public function update(Request $request, string $uuid, string $participationUuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $participation = Participation::with('contact')
            ->where('uuid', $participationUuid)
            ->where('event_id', $event->id)
            ->where('role', 'speaker')
            ->firstOrFail();

        $data = $request->validate([
            'name'        => ['sometimes', 'string', 'max:250'],
            'email'       => ['sometimes', 'email'],
            'designation' => ['nullable', 'string', 'max:250'],
            'company'     => ['nullable', 'string', 'max:250'],
            'bio'         => ['nullable', 'string'],
            'image_url'   => ['nullable', 'string', 'max:2000'],
            'facebook'    => ['nullable', 'string', 'max:500'],
            'linkedin'    => ['nullable', 'string', 'max:500'],
            'twitter'     => ['nullable', 'string', 'max:500'],
            'instagram'   => ['nullable', 'string', 'max:500'],
            'whatsapp'    => ['nullable', 'string', 'max:500'],
            'tags'        => ['nullable', 'array'],
            'tags.*'      => ['string', 'max:100'],
            'is_featured' => ['nullable', 'boolean'],
            'is_public'   => ['nullable', 'boolean'],
        ]);

        if (isset($data['name'])) {
            [$firstName, $lastName] = $this->splitName($data['name']);
            $participation->contact->update(['first_name' => $firstName, 'last_name' => $lastName]);
        }

        if (isset($data['email'])) {
            $participation->contact->update(['email' => $data['email']]);
        }

        // Only merge profile keys that were actually sent in the request.
        $profileUpdate = $request->only([
            'designation', 'company', 'bio', 'image_url',
            'facebook', 'linkedin', 'twitter', 'instagram', 'whatsapp',
            'tags', 'is_featured', 'is_public',
        ]);

        if ($profileUpdate) {
            $participation->update([
                'profile_data' => array_merge($participation->profile_data ?? [], $profileUpdate),
            ]);
        }

        return response()->json(['data' => $this->format($participation->fresh()->load('contact'))]);
    }

    public function destroy(string $uuid, string $participationUuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $participation = Participation::where('uuid', $participationUuid)
            ->where('event_id', $event->id)
            ->where('role', 'speaker')
            ->firstOrFail();

        $participation->delete();

        return response()->json(null, 204);
    }

    private function format(Participation $p): array
    {
        $profile = $p->profile_data ?? [];

        return [
            'id'          => $p->uuid,
            'name'        => $p->contact->fullName(),
            'email'       => $p->contact->email,
            'designation' => $profile['designation'] ?? '',
            'company'     => $profile['company'] ?? '',
            'bio'         => $profile['bio'] ?? '',
            'image_url'   => $profile['image_url'] ?? null,
            'facebook'    => $profile['facebook'] ?? '',
            'linkedin'    => $profile['linkedin'] ?? '',
            'twitter'     => $profile['twitter'] ?? '',
            'instagram'   => $profile['instagram'] ?? '',
            'whatsapp'    => $profile['whatsapp'] ?? '',
            'tags'        => $profile['tags'] ?? [],
            'is_featured' => $profile['is_featured'] ?? false,
            'is_public'   => $profile['is_public'] ?? true,
            'sort_order'  => $profile['sort_order'] ?? 0,
        ];
    }

    private function splitName(string $name): array
    {
        $parts = explode(' ', trim($name), 2);

        return [$parts[0], $parts[1] ?? ''];
    }
}
