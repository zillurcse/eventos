<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Event;
use App\Models\Participation;
use App\Models\SpeakerRating;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
            'name'                   => ['required', 'string', 'max:250'],
            'email'                  => ['required', 'email'],
            'designation'            => ['nullable', 'string', 'max:250'],
            'company'                => ['nullable', 'string', 'max:250'],
            'category'               => ['nullable', 'string', 'max:150'],
            'presentation_title'     => ['nullable', 'string', 'max:250'],
            'presentation_file'      => ['nullable', 'string', 'max:2000'],
            'presentation_file_name' => ['nullable', 'string', 'max:250'],
            'bio'                    => ['nullable', 'string'],
            'image_url'              => ['nullable', 'string', 'max:2000'],
            'facebook'               => ['nullable', 'string', 'max:500'],
            'linkedin'               => ['nullable', 'string', 'max:500'],
            'twitter'                => ['nullable', 'string', 'max:500'],
            'instagram'              => ['nullable', 'string', 'max:500'],
            'whatsapp'               => ['nullable', 'string', 'max:500'],
            'tags'                   => ['nullable', 'array'],
            'tags.*'                 => ['string', 'max:100'],
            'can_rate'               => ['nullable', 'boolean'],
            'is_featured'            => ['nullable', 'boolean'],
            'is_public'              => ['nullable', 'boolean'],
        ]);

        [$firstName, $lastName] = $this->splitName($data['name']);

        $participation = DB::transaction(function () use ($event, $data, $firstName, $lastName) {
            $contact = Contact::firstOrCreate(
                ['email' => $data['email']],
                ['first_name' => $firstName, 'last_name' => $lastName],
            );

            // Keep name fresh even if the contact already existed.
            $contact->update(['first_name' => $firstName, 'last_name' => $lastName]);

            // One speaker per email per event.
            $already = Participation::where('event_id', $event->id)
                ->where('contact_id', $contact->id)
                ->where('role', 'speaker')
                ->exists();

            if ($already) {
                throw ValidationException::withMessages([
                    'email' => ['This email is already a speaker for this event.'],
                ]);
            }

            // Give the speaker a login account (they sign in as a speaker via the
            // same email). No password is collected here, so we seed a random one
            // the speaker can reset later; an existing login is reused as-is.
            if (! $contact->user_id) {
                $user = User::firstOrCreate(
                    ['email' => $data['email']],
                    [
                        'name' => $contact->fullName() ?: $data['email'],
                        'password' => Str::random(40),
                        'email_verified_at' => now(),
                    ],
                );
                $contact->update(['user_id' => $user->id]);
            }

            $participation = new Participation([
                'event_id'   => $event->id,
                'contact_id' => $contact->id,
                'status'     => 'confirmed',
            ]);
            $participation->forceFill(['role' => 'speaker'])->save(); // role: privileged, not $fillable

            return $participation;
        });

        $participation->update([
            'profile_data' => array_merge($participation->profile_data ?? [], [
                'designation'            => $data['designation'] ?? '',
                'company'                => $data['company'] ?? '',
                'category'               => $data['category'] ?? '',
                'presentation_title'     => $data['presentation_title'] ?? '',
                'presentation_file'      => $data['presentation_file'] ?? null,
                'presentation_file_name' => $data['presentation_file_name'] ?? '',
                'bio'                    => $data['bio'] ?? '',
                'image_url'              => $data['image_url'] ?? null,
                'facebook'               => $data['facebook'] ?? '',
                'linkedin'               => $data['linkedin'] ?? '',
                'twitter'                => $data['twitter'] ?? '',
                'instagram'              => $data['instagram'] ?? '',
                'whatsapp'               => $data['whatsapp'] ?? '',
                'tags'                   => $data['tags'] ?? [],
                'can_rate'               => $data['can_rate'] ?? false,
                'is_featured'            => $data['is_featured'] ?? false,
                'is_public'              => $data['is_public'] ?? true,
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
            'name'                   => ['sometimes', 'string', 'max:250'],
            'email'                  => ['sometimes', 'email'],
            'designation'            => ['nullable', 'string', 'max:250'],
            'company'                => ['nullable', 'string', 'max:250'],
            'category'               => ['nullable', 'string', 'max:150'],
            'presentation_title'     => ['nullable', 'string', 'max:250'],
            'presentation_file'      => ['nullable', 'string', 'max:2000'],
            'presentation_file_name' => ['nullable', 'string', 'max:250'],
            'bio'                    => ['nullable', 'string'],
            'image_url'              => ['nullable', 'string', 'max:2000'],
            'facebook'               => ['nullable', 'string', 'max:500'],
            'linkedin'               => ['nullable', 'string', 'max:500'],
            'twitter'                => ['nullable', 'string', 'max:500'],
            'instagram'              => ['nullable', 'string', 'max:500'],
            'whatsapp'               => ['nullable', 'string', 'max:500'],
            'tags'                   => ['nullable', 'array'],
            'tags.*'                 => ['string', 'max:100'],
            'can_rate'               => ['nullable', 'boolean'],
            'is_featured'            => ['nullable', 'boolean'],
            'is_public'              => ['nullable', 'boolean'],
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
            'designation', 'company', 'category',
            'presentation_title', 'presentation_file', 'presentation_file_name',
            'bio', 'image_url',
            'facebook', 'linkedin', 'twitter', 'instagram', 'whatsapp',
            'tags', 'can_rate', 'is_featured', 'is_public',
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

    /**
     * Give a speaker a login (or reset it).
     *
     * A speaker is created as a contact + participation with no User, so until
     * this runs they cannot sign in to the event site at all — which also means
     * they cannot take the stage on their own session (the host is identified by
     * their signed-in participation). Mirrors the exhibitor reset-password flow:
     * auto-generate a password or set one explicitly, and return it once so the
     * organizer can pass it on.
     */
    public function resetPassword(Request $request, string $uuid, string $participationUuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $participation = Participation::with('contact')
            ->where('uuid', $participationUuid)
            ->where('event_id', $event->id)
            ->where('role', 'speaker')
            ->firstOrFail();

        $data = $request->validate([
            'mode' => ['required', 'in:auto,manual'],
            'password' => ['required_if:mode,manual', 'nullable', 'string', 'min:8'],
        ]);

        $contact = $participation->contact;
        if (! $contact?->email) {
            throw ValidationException::withMessages([
                'speaker' => 'This speaker has no email address to attach a login to.',
            ]);
        }

        $password = $data['mode'] === 'auto'
            ? Str::password(14, symbols: false)
            : $data['password'];

        // Identity lives on the migrator connection (users are not tenant-scoped).
        $user = User::on('pgsql_admin')->firstOrNew(['email' => $contact->email]);
        $user->name = $contact->fullName() ?: $contact->email;
        $user->password = $password;               // hashed by the model cast
        $user->email_verified_at ??= now();
        $user->save();

        // Link the contact to the login, otherwise ResolveParticipant can't find
        // this participation when they sign in.
        if ($contact->user_id !== $user->id) {
            $contact->user_id = $user->id;
            $contact->save();
        }

        return response()->json(['data' => [
            'email' => $contact->email,
            'password' => $password,   // shown once, never stored in the clear
        ]]);
    }

    /** The signed-in attendee's own rating for one speaker, plus the current average. */
    public function myRating(Request $request, string $event, string $participationUuid): JsonResponse
    {
        $speaker = $this->participantSpeaker($request, $participationUuid);
        $pid = (int) $request->attributes->get('participation_id');

        $mine = SpeakerRating::where('speaker_participation_id', $speaker->id)
            ->where('participation_id', $pid)
            ->first();

        return response()->json([
            'data' => [
                'score' => $mine?->score,
                ...$this->ratingSummary($speaker),
            ],
        ]);
    }

    /** Create or update the signed-in attendee's rating for a speaker. */
    public function rate(Request $request, string $event, string $participationUuid): JsonResponse
    {
        $speaker = $this->participantSpeaker($request, $participationUuid, mustAllowRating: true);
        $pid = (int) $request->attributes->get('participation_id');

        $data = $request->validate([
            'score' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        $rating = SpeakerRating::updateOrCreate(
            ['speaker_participation_id' => $speaker->id, 'participation_id' => $pid],
            [
                'organization_id' => $speaker->organization_id,
                'event_id' => $speaker->event_id,
                'score' => (int) $data['score'],
                'rated_at' => now(),
            ],
        );

        return response()->json([
            'data' => [
                'score' => $rating->score,
                ...$this->ratingSummary($speaker),
            ],
        ]);
    }

    /** Organizer-facing rating list for one speaker, including averages. */
    public function ratings(string $uuid, string $participationUuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $speaker = Participation::with(['speakerRatings.participation.contact', 'contact'])
            ->where('uuid', $participationUuid)
            ->where('event_id', $event->id)
            ->where('role', 'speaker')
            ->firstOrFail();

        $summary = $this->ratingSummary($speaker);

        $ratings = $speaker->speakerRatings
            ->sortByDesc(fn (SpeakerRating $rating) => $rating->rated_at?->getTimestamp() ?? $rating->created_at?->getTimestamp() ?? 0)
            ->values()
            ->map(function (SpeakerRating $rating) {
                $contact = $rating->participation?->contact;
                $name = trim(($contact->first_name ?? '').' '.($contact->last_name ?? ''));

                return [
                    'id' => $rating->uuid,
                    'score' => $rating->score,
                    'rated_at' => ($rating->rated_at ?? $rating->created_at)?->toIso8601String(),
                    'participation' => [
                        'id' => $rating->participation?->uuid,
                        'role' => $rating->participation?->role,
                        'status' => $rating->participation?->status,
                        'name' => $name !== '' ? $name : null,
                        'email' => $contact?->email,
                    ],
                ];
            });

        return response()->json([
            'data' => [
                'speaker' => [
                    'id' => $speaker->uuid,
                    'name' => $speaker->contact?->fullName(),
                ],
                'summary' => $summary,
                'ratings' => $ratings,
            ],
        ]);
    }

    private function participantSpeaker(Request $request, string $participationUuid, bool $mustAllowRating = false): Participation
    {
        $speaker = Participation::where('event_id', (int) $request->attributes->get('event_id'))
            ->where('uuid', $participationUuid)
            ->where('role', 'speaker')
            ->firstOrFail();

        if ($mustAllowRating && ! (bool) (($speaker->profile_data['can_rate'] ?? false))) {
            throw ValidationException::withMessages([
                'score' => 'Rating is disabled for this speaker.',
            ]);
        }

        return $speaker;
    }

    private function ratingSummary(Participation $speaker): array
    {
        $query = SpeakerRating::where('speaker_participation_id', $speaker->id);
        $count = (int) (clone $query)->count();
        $average = $count > 0 ? round((float) (clone $query)->avg('score'), 1) : null;
        $distribution = (clone $query)
            ->selectRaw('score, count(*) as total')
            ->groupBy('score')
            ->pluck('total', 'score');

        return [
            'ratings_count' => $count,
            'average_score' => $average,
            'distribution' => collect(range(1, 5))
                ->map(fn (int $score) => [
                    'score' => $score,
                    'count' => (int) ($distribution[$score] ?? 0),
                ])
                ->all(),
        ];
    }

    private function format(Participation $p): array
    {
        $profile = $p->profile_data ?? [];

        return [
            'id'                     => $p->uuid,
            'name'                   => $p->contact->fullName(),
            'email'                  => $p->contact->email,
            'has_login'              => ! empty($p->contact->user_id),
            'designation'            => $profile['designation'] ?? '',
            'company'                => $profile['company'] ?? '',
            'category'               => $profile['category'] ?? '',
            'presentation_title'     => $profile['presentation_title'] ?? '',
            'presentation_file'      => $profile['presentation_file'] ?? null,
            'presentation_file_name' => $profile['presentation_file_name'] ?? '',
            'bio'                    => $profile['bio'] ?? '',
            'image_url'              => $profile['image_url'] ?? null,
            'facebook'               => $profile['facebook'] ?? '',
            'linkedin'               => $profile['linkedin'] ?? '',
            'twitter'                => $profile['twitter'] ?? '',
            'instagram'              => $profile['instagram'] ?? '',
            'whatsapp'               => $profile['whatsapp'] ?? '',
            'tags'                   => $profile['tags'] ?? [],
            'can_rate'               => $profile['can_rate'] ?? false,
            'is_featured'            => $profile['is_featured'] ?? false,
            'is_public'              => $profile['is_public'] ?? true,
            'sort_order'             => $profile['sort_order'] ?? 0,
        ];
    }

    // ── Speaker categories (event-level list stored in event.meta) ───────────

    /** List the event's speaker categories. */
    public function categories(string $uuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        return response()->json(['data' => $this->categoriesOf($event)]);
    }

    public function storeCategory(Request $request, string $uuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $data = $request->validate(['name' => ['required', 'string', 'max:150']]);

        $categories = $this->categoriesOf($event);
        $name = trim($data['name']);

        // Ignore duplicates (case-insensitive) — just return the existing list.
        if (! $this->hasCategory($categories, $name)) {
            $categories[] = ['id' => (string) Str::uuid(), 'name' => $name];
            $this->saveCategories($event, $categories);
        }

        return response()->json(['data' => $categories], 201);
    }

    public function updateCategory(Request $request, string $uuid, string $categoryId): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $data = $request->validate(['name' => ['required', 'string', 'max:150']]);
        $name = trim($data['name']);

        $categories = $this->categoriesOf($event);
        $old = null;

        foreach ($categories as &$cat) {
            if ($cat['id'] === $categoryId) {
                $old = $cat['name'];
                $cat['name'] = $name;
                break;
            }
        }
        unset($cat);

        $this->saveCategories($event, $categories);

        // Keep speakers referencing the old name in sync with the rename.
        if ($old !== null && $old !== $name) {
            $this->renameSpeakerCategory($event, $old, $name);
        }

        return response()->json(['data' => $categories]);
    }

    public function destroyCategory(string $uuid, string $categoryId): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $categories = array_values(array_filter(
            $this->categoriesOf($event),
            fn (array $c) => $c['id'] !== $categoryId,
        ));

        $this->saveCategories($event, $categories);

        return response()->json(['data' => $categories]);
    }

    /** @return array<int, array{id: string, name: string}> */
    private function categoriesOf(Event $event): array
    {
        return $event->meta['speaker_categories'] ?? [];
    }

    private function hasCategory(array $categories, string $name): bool
    {
        return collect($categories)->contains(
            fn (array $c) => mb_strtolower($c['name']) === mb_strtolower($name),
        );
    }

    private function saveCategories(Event $event, array $categories): void
    {
        $event->update([
            'meta' => array_merge($event->meta ?? [], ['speaker_categories' => $categories]),
        ]);
    }

    private function renameSpeakerCategory(Event $event, string $old, string $new): void
    {
        Participation::where('event_id', $event->id)
            ->speakers()
            ->get()
            ->each(function (Participation $p) use ($old, $new) {
                $profile = $p->profile_data ?? [];
                if (($profile['category'] ?? '') === $old) {
                    $profile['category'] = $new;
                    $p->update(['profile_data' => $profile]);
                }
            });
    }

    private function splitName(string $name): array
    {
        $parts = explode(' ', trim($name), 2);

        return [$parts[0], $parts[1] ?? ''];
    }
}
