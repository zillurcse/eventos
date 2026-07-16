<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EventSetting;
use App\Models\Participation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The signed-in attendee's own profile — and the onboarding step that fills it
 * in (Settings › Onboarding).
 *
 * Onboarding here means "complete your profile before you land on Reception":
 * the photo, job title, company and interests that make the delegate directory,
 * the meeting requests and the "people like you" strip worth anything. An event
 * where nobody filled those in is a networking app with nothing to network on,
 * which is why the organizer gets to insist on it.
 *
 * `needs_onboarding` is computed here rather than left to the client: the client
 * would have to know both the organizer's setting and what "complete" means, and
 * two clients would eventually disagree.
 */
class ParticipantProfileController extends Controller
{
    /** GET /events/{event}/profile */
    public function show(Request $request): JsonResponse
    {
        $participation = $this->participation($request);

        return response()->json([
            'data' => $this->profile($participation),
            'meta' => [
                'needs_onboarding' => $this->needsOnboarding($request, $participation),
            ],
        ]);
    }

    /** PUT /events/{event}/profile — the onboarding form saves here. */
    public function update(Request $request): JsonResponse
    {
        $participation = $this->participation($request);

        $data = $request->validate([
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'job_title' => ['nullable', 'string', 'max:150'],
            'company' => ['nullable', 'string', 'max:150'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'avatar_url' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:40'],
            'gender' => ['nullable', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'purpose_of_visit' => ['nullable', 'string', 'max:300'],
            'purchasing_decision' => ['nullable', 'string', 'max:300'],
            'language' => ['nullable', 'string', 'max:20'],
            'timezone' => ['nullable', 'string', 'max:60'],
            'interests' => ['nullable', 'array', 'max:12'],
            'interests.*' => ['string', 'max:40'],
            'looking_for' => ['nullable', 'array', 'max:12'],
            'looking_for.*' => ['string', 'max:40'],
            'offering' => ['nullable', 'array', 'max:12'],
            'offering.*' => ['string', 'max:40'],
            'social' => ['nullable', 'array'],
            'social.linkedin' => ['nullable', 'string', 'max:300'],
            'social.twitter' => ['nullable', 'string', 'max:300'],
            'social.website' => ['nullable', 'string', 'max:300'],
            // Set when the attendee finishes (or skips) the onboarding step, so
            // we stop asking. Skipping is allowed on purpose: a hard gate in
            // front of an event someone is already late for is a way to lose them.
            'complete_onboarding' => ['nullable', 'boolean'],
        ]);

        // First/last name live on the shared Contact record (name is global to
        // the person, not per-event), so they're saved there rather than into
        // this participation's profile_data.
        if ($participation->contact && (isset($data['first_name']) || isset($data['last_name']))) {
            $participation->contact->fill(collect($data)->only(['first_name', 'last_name'])->all())->save();
        }

        $profile = array_merge(
            $participation->profile_data ?? [],
            collect($data)
                ->except(['complete_onboarding', 'first_name', 'last_name'])
                ->filter(fn ($v) => $v !== null)
                ->all(),
        );

        $meta = $participation->meta ?? [];
        if ($request->boolean('complete_onboarding')) {
            $meta['onboarded_at'] = now()->toIso8601String();
        }

        $participation->update(['profile_data' => $profile, 'meta' => $meta]);

        $fresh = $participation->fresh(['contact']);

        return response()->json([
            'data' => $this->profile($fresh),
            'meta' => ['needs_onboarding' => $this->needsOnboarding($request, $fresh)],
        ]);
    }

    /**
     * Ask once. Someone who has been through the step — or who already has a
     * job title and company from registration — is not made to do it again.
     */
    private function needsOnboarding(Request $request, Participation $participation): bool
    {
        $setting = EventSetting::on('pgsql_admin')
            ->where('event_id', $request->attributes->get('event_id'))
            ->first();

        if (! ($setting?->login['onboarding'] ?? false)) {
            return false;
        }

        if (! empty($participation->meta['onboarded_at'])) {
            return false;
        }

        $profile = $participation->profile_data ?? [];

        return empty($profile['job_title']) && empty($profile['company']);
    }

    private function profile(Participation $participation): array
    {
        $profile = $participation->profile_data ?? [];
        $contact = $participation->contact;

        return [
            'name' => $contact?->fullName(),
            'first_name' => $contact?->first_name ?? '',
            'last_name' => $contact?->last_name ?? '',
            'email' => $contact?->email,
            'job_title' => $profile['job_title'] ?? ($profile['designation'] ?? ''),
            'company' => $profile['company'] ?? '',
            'bio' => $profile['bio'] ?? '',
            'avatar_url' => $profile['avatar_url'] ?? ($profile['image_url'] ?? null),
            'phone' => $profile['phone'] ?? '',
            'gender' => $profile['gender'] ?? '',
            'country' => $profile['country'] ?? '',
            'state' => $profile['state'] ?? '',
            'city' => $profile['city'] ?? '',
            'zip_code' => $profile['zip_code'] ?? '',
            'purpose_of_visit' => $profile['purpose_of_visit'] ?? '',
            'purchasing_decision' => $profile['purchasing_decision'] ?? '',
            'language' => $profile['language'] ?? '',
            'timezone' => $profile['timezone'] ?? '',
            'interests' => $profile['interests'] ?? [],
            'looking_for' => $profile['looking_for'] ?? [],
            'offering' => $profile['offering'] ?? [],
            'social' => $profile['social'] ?? [],
            'onboarded_at' => $participation->meta['onboarded_at'] ?? null,
        ];
    }

    private function participation(Request $request): Participation
    {
        return Participation::with('contact')
            ->findOrFail((int) $request->attributes->get('participation_id'));
    }
}
