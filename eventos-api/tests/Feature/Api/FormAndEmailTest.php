<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Form Builder + Email Builder engines (organizer side). */
class FormAndEmailTest extends TestCase
{
    use DatabaseTransactions;

    public function test_form_can_be_created_edited_and_published(): void
    {
        $this->actingAsOrganizer();

        $form = $this->postJson('/api/v1/forms', [
            'name' => 'Registration Form',
            'target_entity' => 'participation',
            'fields' => [
                ['key' => 'full_name', 'type' => 'text', 'label' => 'Full name', 'is_required' => true],
                ['key' => 'email', 'type' => 'email', 'label' => 'Email', 'is_required' => true],
            ],
        ])->assertCreated()->json('data');

        $uuid = $form['id'];

        $this->getJson('/api/v1/forms')->assertOk()->assertJsonStructure(['data']);

        $this->getJson("/api/v1/forms/{$uuid}/edit")
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'fields']]);

        $this->postJson("/api/v1/forms/{$uuid}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        // NOTE: the public render endpoint (GET /forms/{uuid}) reads on the
        // pgsql_admin connection, so it cannot see a form created inside this
        // test's rolled-back transaction — that path is covered by the
        // input-guard test below rather than a happy-path read here.
    }

    public function test_public_form_render_rejects_unknown_token(): void
    {
        $this->getJson('/api/v1/forms/00000000-0000-0000-0000-000000000000')
            ->assertNotFound();
    }

    public function test_form_creation_requires_a_name(): void
    {
        $this->actingAsOrganizer();

        $this->postJson('/api/v1/forms', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_email_template_can_be_created_and_listed(): void
    {
        $this->actingAsOrganizer();

        $uuid = $this->postJson('/api/v1/email-templates', [
            'name' => 'Welcome Email',
            'subject' => 'Welcome, {{first_name}}',
            'blocks' => [],
        ])->assertCreated()->json('data.id');

        $this->getJson('/api/v1/email-templates')->assertOk()->assertJsonStructure(['data']);

        $this->getJson("/api/v1/email-templates/{$uuid}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['id']]);

        $this->postJson("/api/v1/email-templates/{$uuid}/preview", [])
            ->assertOk()
            ->assertJsonStructure(['html']);
    }
}
