<?php

namespace Tests\Feature\Api;

use App\Models\Form;
use App\Models\FormField;
use App\Services\Forms\FormValidatorBuilder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Event Settings › Profile — per-audience profile forms (builder, publish,
 * reset, submissions listing).
 *
 * The public render/submit endpoints read on pgsql_admin (BYPASSRLS), so they
 * can't see forms created inside this rolled-back transaction; the surface
 * filtering they rely on is covered by the in-memory validator test instead.
 */
class ProfileFormsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_profile_forms_are_provisioned_per_audience(): void
    {
        $this->actingAsOrganizer();
        $event = $this->createEvent();

        $rows = $this->getJson("/api/v1/events/{$event['id']}/profile-forms")
            ->assertOk()
            ->json('data');

        $this->assertSame(
            ['attendee', 'speaker', 'exhibitor', 'sponsor', 'organizer'],
            array_column($rows, 'audience'),
        );

        foreach ($rows as $row) {
            $this->assertSame('draft', $row['status']);
            $this->assertGreaterThan(0, $row['fields_count']);
            $this->assertSame(0, $row['submissions_count']);
        }
    }

    public function test_builder_saves_custom_fields_and_hides_omitted_defaults(): void
    {
        $this->actingAsOrganizer();
        $event = $this->createEvent();

        $form = $this->getJson("/api/v1/events/{$event['id']}/profile-forms/attendee")
            ->assertOk()->json('data');

        // Keep every default except `phone`, then add a dropdown of our own.
        $fields = collect($form['fields'])
            ->reject(fn ($f) => $f['key'] === 'phone')
            ->map(fn ($f) => [
                'key' => $f['key'],
                'type' => $f['type'],
                'label' => $f['label'],
                'is_required' => $f['required'],
                'meta' => $f['meta'],
                'options' => collect($f['options'] ?? [])->map(fn ($o) => ['label' => $o['label'], 'value' => $o['value']])->all(),
            ])
            ->push([
                'key' => 'tshirt_size',
                'type' => 'select',
                'label' => 'T-shirt size',
                'is_required' => true,
                'meta' => ['width' => 50, 'visible' => true, 'surfaces' => ['public' => true, 'onboarding' => false]],
                'options' => [['label' => 'S'], ['label' => 'M'], ['label' => 'L']],
            ])
            ->values()->all();

        $saved = $this->putJson("/api/v1/events/{$event['id']}/profile-forms/attendee", ['fields' => $fields])
            ->assertOk()->json('data');

        $byKey = collect($saved['fields'])->keyBy('key');

        // The custom field landed with its meta + options.
        $this->assertTrue($byKey->has('tshirt_size'));
        $this->assertSame(50, $byKey['tshirt_size']['meta']['width']);
        $this->assertFalse($byKey['tshirt_size']['meta']['surfaces']['onboarding']);
        $this->assertCount(3, $byKey['tshirt_size']['options']);

        // The omitted default was hidden, not deleted.
        $this->assertTrue($byKey->has('phone'));
        $this->assertFalse($byKey['phone']['meta']['visible']);
    }

    public function test_builder_rejects_bad_field_keys_and_types(): void
    {
        $this->actingAsOrganizer();
        $event = $this->createEvent();

        $this->putJson("/api/v1/events/{$event['id']}/profile-forms/attendee", [
            'fields' => [['key' => 'Bad Key!', 'type' => 'text']],
        ])->assertStatus(422)->assertJsonValidationErrors(['fields.0.key']);

        $this->putJson("/api/v1/events/{$event['id']}/profile-forms/attendee", [
            'fields' => [['key' => 'ok_key', 'type' => 'hologram']],
        ])->assertStatus(422)->assertJsonValidationErrors(['fields.0.type']);
    }

    public function test_publish_bumps_version_and_reset_restores_defaults(): void
    {
        $this->actingAsOrganizer();
        $event = $this->createEvent();

        $this->getJson("/api/v1/events/{$event['id']}/profile-forms/speaker")->assertOk();

        $published = $this->postJson("/api/v1/events/{$event['id']}/profile-forms/speaker/publish")
            ->assertOk()->json('data');
        $this->assertSame('published', $published['status']);
        $this->assertSame(2, $published['version']);

        // Add a custom field, then reset — the fresh draft has defaults only.
        $fields = collect($published['fields'])->map(fn ($f) => ['key' => $f['key'], 'type' => $f['type'], 'label' => $f['label']])
            ->push(['key' => 'travel_needs', 'type' => 'textarea', 'label' => 'Travel needs'])
            ->values()->all();
        $this->putJson("/api/v1/events/{$event['id']}/profile-forms/speaker", ['fields' => $fields])->assertOk();

        $fresh = $this->deleteJson("/api/v1/events/{$event['id']}/profile-forms/speaker")
            ->assertOk()->json('data');

        $this->assertSame('draft', $fresh['status']);
        $this->assertNotContains('travel_needs', array_column($fresh['fields'], 'key'));
        $this->assertContains('talk_title', array_column($fresh['fields'], 'key'));
    }

    public function test_unknown_audience_is_a_404(): void
    {
        $this->actingAsOrganizer();
        $event = $this->createEvent();

        $this->getJson("/api/v1/events/{$event['id']}/profile-forms/aliens")->assertNotFound();
    }

    public function test_submissions_listing_starts_empty(): void
    {
        $this->actingAsOrganizer();
        $event = $this->createEvent();

        $this->getJson("/api/v1/events/{$event['id']}/profile-forms/exhibitor/submissions")
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.audience', 'exhibitor');
    }

    public function test_design_settings_persist_and_reject_junk(): void
    {
        $this->actingAsOrganizer();
        $event = $this->createEvent();

        $form = $this->getJson("/api/v1/events/{$event['id']}/profile-forms/exhibitor")->assertOk()->json('data');
        $fields = collect($form['fields'])->map(fn ($f) => ['key' => $f['key'], 'type' => $f['type']])->all();

        $saved = $this->putJson("/api/v1/events/{$event['id']}/profile-forms/exhibitor", [
            'fields' => $fields,
            'design' => [
                'background_type' => 'color',
                'background_color' => '#1E293B',
                'brand_color' => '#22d3ee',
                'card_style' => 'glass',
                'show_header' => false,
            ],
        ])->assertOk()->json('data');

        $this->assertSame('#1E293B', $saved['settings']['design']['background_color']);
        $this->assertSame('glass', $saved['settings']['design']['card_style']);
        $this->assertFalse($saved['settings']['design']['show_header']);

        // A colour is inlined into a public page's styles, so anything that is
        // not a plain hex is refused rather than stored.
        $this->putJson("/api/v1/events/{$event['id']}/profile-forms/exhibitor", [
            'fields' => $fields,
            'design' => ['background_color' => 'javascript:alert(1)'],
        ])->assertStatus(422)->assertJsonValidationErrors(['design.background_color']);

        $this->putJson("/api/v1/events/{$event['id']}/profile-forms/exhibitor", [
            'fields' => $fields,
            'design' => ['card_style' => 'neon'],
        ])->assertStatus(422)->assertJsonValidationErrors(['design.card_style']);

        // Saving fields without a design payload leaves the design alone.
        $after = $this->putJson("/api/v1/events/{$event['id']}/profile-forms/exhibitor", ['fields' => $fields])
            ->assertOk()->json('data');
        $this->assertSame('glass', $after['settings']['design']['card_style']);
    }

    public function test_submissions_export_returns_a_csv_with_a_column_per_field(): void
    {
        $this->actingAsOrganizer();
        $event = $this->createEvent();

        $res = $this->postJson("/api/v1/events/{$event['id']}/profile-forms/sponsor/submissions/export")
            ->assertOk()->json('data');

        $this->assertSame(0, $res['count']);
        $this->assertStringEndsWith('.csv', $res['filename']);

        $header = strtok($res['csv'], "\r\n");
        foreach (['Submitted at', 'Source', 'Status', 'Company name', 'Sponsorship tier of interest'] as $column) {
            $this->assertStringContainsString($column, $header);
        }
    }

    public function test_surface_keys_exclude_hidden_fields_but_registration_keeps_email(): void
    {
        $form = new Form(['name' => 'Attendee', 'key' => 'profile.attendee']);
        $form->setRelation('fields', collect([
            // Email is switched OFF for registration — signup must keep it anyway.
            $this->field(['key' => 'email', 'type' => 'email', 'meta' => ['surfaces' => ['registration' => false]]]),
            $this->field(['key' => 'company', 'type' => 'text']),
            $this->field(['key' => 'secret', 'type' => 'text', 'meta' => ['visible' => false]]),
            $this->field(['key' => 'why_here', 'type' => 'text', 'meta' => ['surfaces' => ['public' => false]]]),
            $this->field(['key' => 'divider', 'type' => 'section_break']),
        ]));

        $this->assertTrue($form->isProfileForm());
        $this->assertSame(['email', 'company'], $form->surfaceKeys('public'));
        $this->assertSame(['company', 'why_here'], $form->surfaceKeys('registration'));
        $this->assertSame(['company', 'why_here', 'email'], $form->registrationKeys());
    }

    public function test_validator_only_restricts_to_the_shown_surface(): void
    {
        // In-memory form: a required field the public surface hides must not
        // block a public submission, while shown required fields still do.
        $form = new Form(['name' => 'Attendee', 'key' => 'profile.attendee', 'version' => 1]);
        $form->setRelation('fields', collect([
            $this->field(['key' => 'email', 'type' => 'email', 'is_required' => true]),
            $this->field(['key' => 'purpose', 'type' => 'text', 'is_required' => true]), // hidden on public
        ]));

        $builder = app(FormValidatorBuilder::class);

        $validated = $builder->validate($form, ['email' => 'jo@example.test'], ['email']);
        $this->assertSame(['email' => 'jo@example.test'], $validated);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $builder->validate($form, ['purpose' => 'networking'], ['email', 'purpose']);
    }

    private function field(array $attrs): FormField
    {
        $f = new FormField($attrs);
        $f->setRelation('options', collect());

        return $f;
    }
}
