<?php

namespace Tests\Feature\Api;

use App\Models\BadgeDesign;
use App\Models\Event;
use App\Models\Participation;
use App\Services\Badges\BadgeRenderData;
use App\Services\Badges\BadgeResolver;
use App\Support\BadgeAudience;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Role-based and guest badges: the starter templates, the resolution ladder
 * that decides which design a person is printed on, and the guest-badge wizard's
 * endpoints.
 */
class BadgeTest extends TestCase
{
    use DatabaseTransactions;

    private function event(): array
    {
        $this->actingAsOrganizer();

        return $this->createEvent();
    }

    // ── Starter templates ────────────────────────────────────────────────────

    public function test_seed_defaults_creates_one_badge_per_audience(): void
    {
        $event = $this->event();

        $res = $this->postJson("/api/v1/events/{$event['id']}/badge-designs/seed-defaults")
            ->assertCreated();

        $this->assertSame(4, $res->json('meta.created'));

        $audiences = collect($res->json('data'))->pluck('badge_for')->sort()->values()->all();
        $this->assertSame(['attendee', 'exhibitor', 'speaker', 'sponsor'], $audiences);

        // The attendee badge is the fallback for anyone with no design of their own.
        $default = collect($res->json('data'))->firstWhere('is_default', true);
        $this->assertSame('attendee', $default['badge_for']);
    }

    public function test_seed_defaults_is_idempotent(): void
    {
        $event = $this->event();

        $this->postJson("/api/v1/events/{$event['id']}/badge-designs/seed-defaults")->assertCreated();

        $this->postJson("/api/v1/events/{$event['id']}/badge-designs/seed-defaults")
            ->assertCreated()
            ->assertJsonPath('meta.created', 0);
    }

    public function test_starter_design_carries_the_merge_keys_the_renderer_expects(): void
    {
        $event = $this->event();

        $design = $this->postJson("/api/v1/events/{$event['id']}/badge-designs/seed-defaults")
            ->json('data.0');

        $keys = collect($design['badge_json']['frontBoxes'])->pluck('key');

        // Every dynamic box must name a token BadgeRenderData actually produces,
        // or it silently prints its placeholder onto real card stock.
        foreach (['full_name', 'company', 'designation', 'event_name', 'qrcode'] as $key) {
            $this->assertTrue($keys->contains($key), "starter design is missing the '{$key}' box");
        }

        $unknown = $keys->reject(fn ($k) => in_array($k, BadgeRenderData::KEYS, true));
        $this->assertTrue($unknown->isEmpty(), 'unknown merge keys: '.$unknown->implode(', '));
    }

    public function test_badge_for_rejects_values_outside_the_audience_vocabulary(): void
    {
        $event = $this->event();

        $this->postJson("/api/v1/events/{$event['id']}/badge-designs", [
            'name' => 'Nope',
            'badge_for' => 'partner_member', // a participation role, not an audience
        ])->assertJsonValidationErrors('badge_for');
    }

    // ── Resolution ladder ────────────────────────────────────────────────────

    public function test_resolver_prefers_the_audience_design_over_the_default(): void
    {
        $event = $this->event();
        $this->postJson("/api/v1/events/{$event['id']}/badge-designs/seed-defaults")->assertCreated();

        $model = Event::where('uuid', $event['id'])->firstOrFail();
        $speaker = $this->participation($model, 'speaker');

        $design = app(BadgeResolver::class)->forParticipation($speaker);

        $this->assertSame('speaker', $design->badge_for);
    }

    public function test_resolver_falls_back_to_the_default_when_the_audience_has_no_design(): void
    {
        $event = $this->event();
        $model = Event::where('uuid', $event['id'])->firstOrFail();

        BadgeDesign::create([
            'event_id' => $model->id,
            'name' => 'Only one',
            'badge_for' => 'attendee',
            'is_default' => true,
        ]);

        $staff = $this->participation($model, 'staff');

        $this->assertSame('Only one', app(BadgeResolver::class)->forParticipation($staff)->name);
    }

    public function test_resolver_returns_null_when_the_event_has_no_designs(): void
    {
        $event = $this->event();
        $model = Event::where('uuid', $event['id'])->firstOrFail();

        $this->assertNull(
            app(BadgeResolver::class)->forParticipation($this->participation($model, 'attendee'))
        );
    }

    public function test_a_pinned_design_beats_the_audience_design(): void
    {
        $event = $this->event();
        $model = Event::where('uuid', $event['id'])->firstOrFail();
        $this->postJson("/api/v1/events/{$event['id']}/badge-designs/seed-defaults")->assertCreated();

        $pinned = BadgeDesign::where('event_id', $model->id)->where('badge_for', 'sponsor')->firstOrFail();

        $speaker = $this->participation($model, 'speaker');
        $speaker->update(['meta' => ['badge_design_id' => $pinned->id]]);

        $this->assertSame($pinned->id, app(BadgeResolver::class)->forParticipation($speaker)->id);
    }

    public function test_guest_sub_type_selects_the_matching_guest_design(): void
    {
        $event = $this->event();
        $model = Event::where('uuid', $event['id'])->firstOrFail();

        foreach (['Media', 'VVIP'] as $type) {
            BadgeDesign::create([
                'event_id' => $model->id,
                'name' => "{$type} Pass",
                'badge_for' => 'guest',
                'meta' => ['guest_type' => $type],
            ]);
        }

        $guest = $this->participation($model, 'guest');
        $guest->update(['meta' => ['guest_type' => 'VVIP']]);

        $this->assertSame('VVIP Pass', app(BadgeResolver::class)->forParticipation($guest)->name);
        // The sub-type also becomes what the badge calls them.
        $this->assertSame('VVIP', app(BadgeRenderData::class)->for($guest)['role_label']);
    }

    public function test_guest_type_wins_over_the_participation_role(): void
    {
        $event = $this->event();
        $model = Event::where('uuid', $event['id'])->firstOrFail();

        // A press pass issued to someone who also registered is still a press pass.
        $attendee = $this->participation($model, 'attendee');
        $attendee->update(['meta' => ['guest_type' => 'Media']]);

        $this->assertSame(BadgeAudience::Guest, BadgeAudience::forParticipation($attendee));
    }

    // ── Guest badge wizard ───────────────────────────────────────────────────

    public function test_creating_a_guest_batch_also_creates_its_design(): void
    {
        $event = $this->event();

        $batch = $this->postJson("/api/v1/events/{$event['id']}/guest-badges", [
            'name' => 'Media Passes',
            'guest_type' => 'Media',
        ])->assertCreated()->json('data');

        $this->assertSame('Media', $batch['guest_type']);
        $this->assertSame('guest', $batch['design']['badge_for']);
        $this->assertSame('Media', $batch['design']['guest_type']);
        $this->assertSame(0, $batch['guest_count']);
    }

    public function test_importing_guests_is_idempotent_on_email(): void
    {
        $event = $this->event();
        $batch = $this->postJson("/api/v1/events/{$event['id']}/guest-badges", [
            'name' => 'Media Passes', 'guest_type' => 'Media',
        ])->json('data');

        $guests = ['guests' => [
            ['full_name' => 'Vikram Desai', 'email' => 'vikram@example.test', 'company' => 'TCS'],
            ['full_name' => 'Rahul Mehta', 'email' => 'rahul@example.test'],
        ]];

        $this->postJson("/api/v1/guest-badges/{$batch['id']}/guests", $guests)
            ->assertCreated()
            ->assertJsonPath('meta.created', 2);

        // Re-importing the same list updates those people rather than doubling them.
        $this->postJson("/api/v1/guest-badges/{$batch['id']}/guests", $guests)
            ->assertCreated()
            ->assertJsonPath('meta.created', 0)
            ->assertJsonPath('meta.updated', 2);

        $this->getJson("/api/v1/guest-badges/{$batch['id']}")
            ->assertOk()
            ->assertJsonPath('data.guest_count', 2);
    }

    public function test_importing_a_guest_does_not_hijack_an_existing_participation(): void
    {
        $event = $this->event();
        $model = Event::where('uuid', $event['id'])->firstOrFail();
        $batch = $this->postJson("/api/v1/events/{$event['id']}/guest-badges", [
            'name' => 'Media Passes', 'guest_type' => 'Media',
        ])->json('data');

        $attendee = $this->participation($model, 'attendee');
        $email = $attendee->contact->email;

        $this->postJson("/api/v1/guest-badges/{$batch['id']}/guests", [
            'guests' => [['full_name' => 'Someone', 'email' => $email]],
        ])->assertCreated();

        // They keep their attendee pass and gain a separate guest one.
        $this->assertSame('attendee', $attendee->fresh()->role);
        $this->assertDatabaseHas('participations', [
            'event_id' => $model->id,
            'contact_id' => $attendee->contact_id,
            'role' => 'guest',
        ]);
    }

    public function test_guest_render_data_carries_the_scannable_uuid(): void
    {
        $event = $this->event();
        $batch = $this->postJson("/api/v1/events/{$event['id']}/guest-badges", [
            'name' => 'Media Passes', 'guest_type' => 'Media',
        ])->json('data');

        $this->postJson("/api/v1/guest-badges/{$batch['id']}/guests", [
            'guests' => [['full_name' => 'Vikram Desai', 'company' => 'TCS', 'designation' => 'CIO']],
        ])->assertCreated();

        $guest = $this->getJson("/api/v1/guest-badges/{$batch['id']}")->json('data.guests.0');

        $this->assertSame('Vikram Desai', $guest['render']['full_name']);
        $this->assertSame('TCS', $guest['render']['company']);
        $this->assertSame('Media', $guest['render']['role_label']);
        // The QR encodes the participation uuid — what the gates already scan.
        $this->assertSame($guest['id'], $guest['render']['qrcode']);
    }

    public function test_deleting_a_batch_removes_the_guests_it_created(): void
    {
        $event = $this->event();
        $batch = $this->postJson("/api/v1/events/{$event['id']}/guest-badges", [
            'name' => 'Media Passes', 'guest_type' => 'Media',
        ])->json('data');

        $this->postJson("/api/v1/guest-badges/{$batch['id']}/guests", [
            'guests' => [['full_name' => 'Vikram Desai']],
        ])->assertCreated();

        $id = $this->getJson("/api/v1/guest-badges/{$batch['id']}")->json('data.guests.0.id');

        $this->deleteJson("/api/v1/guest-badges/{$batch['id']}")->assertOk();

        $this->assertSoftDeleted('participations', ['uuid' => $id]);
    }

    /** A participation in the given role, with a contact behind it. */
    private function participation(Event $event, string $role): Participation
    {
        $contact = \App\Models\Contact::create([
            'first_name' => ucfirst($role),
            'last_name' => 'Person',
            'email' => $role.'-'.uniqid().'@example.test',
        ]);

        $participation = new Participation([
            'event_id' => $event->id,
            'contact_id' => $contact->id,
            'status' => 'confirmed',
        ]);
        $participation->forceFill(['role' => $role])->save();

        return $participation->load('contact', 'event');
    }
}
