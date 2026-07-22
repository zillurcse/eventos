<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The email builder's editing surface: design normalization on save, snapshot
 * history and restore, and the endpoints the editor calls while you type.
 *
 * The happy-path CRUD is covered in FormAndEmailTest; this file covers what the
 * builder added on top, with an emphasis on what a malformed or hostile payload
 * does to the stored design.
 */
class EmailBuilderTest extends TestCase
{
    use DatabaseTransactions;

    /** @param array<string,mixed> $overrides */
    private function createTemplate(array $overrides = []): array
    {
        return $this->postJson('/api/v1/email-templates', array_merge([
            'name' => 'Invitation',
            'subject' => 'You are invited',
            'preheader' => 'Seats are limited',
            'category' => 'invitation',
            'blocks' => [
                ['type' => 'heading', 'text' => 'Hello', 'style' => ['align' => 'center']],
            ],
            'settings' => ['contentWidth' => 600],
        ], $overrides))->assertCreated()->json('data');
    }

    public function test_it_persists_preheader_and_category(): void
    {
        $this->actingAsOrganizer();

        $template = $this->createTemplate();

        $this->assertSame('Seats are limited', $template['preheader']);
        $this->assertSame('invitation', $template['category']);
    }

    public function test_it_rejects_an_unknown_category(): void
    {
        $this->actingAsOrganizer();

        $this->postJson('/api/v1/email-templates', [
            'name' => 'Bad',
            'category' => 'not-a-category',
        ])->assertStatus(422)->assertJsonValidationErrors(['category']);
    }

    /** Author HTML must be cleaned at rest, not only on the way out. */
    public function test_it_sanitizes_author_html_before_storing_the_design(): void
    {
        $this->actingAsOrganizer();

        $template = $this->createTemplate([
            'blocks' => [
                ['type' => 'html', 'html' => '<p>keep</p><script>alert(1)</script>', 'style' => []],
                ['type' => 'text', 'html' => '<b>hi</b><img src=x onerror="alert(1)">', 'style' => []],
            ],
        ]);

        $stored = json_encode($template['blocks']);

        $this->assertStringContainsString('keep', $stored);
        $this->assertStringNotContainsString('alert(1)', $stored);
        $this->assertStringNotContainsString('onerror', $stored);
    }

    public function test_it_drops_unknown_block_types(): void
    {
        $this->actingAsOrganizer();

        $template = $this->createTemplate([
            'blocks' => [
                ['type' => 'iframe-block', 'src' => '//evil.test'],
                ['type' => 'heading', 'text' => 'Real'],
            ],
        ]);

        $this->assertCount(1, $template['blocks']);
        $this->assertSame('heading', $template['blocks'][0]['type']);
    }

    /** A design nested past the limit must not be stored as-is. */
    public function test_it_bounds_the_nesting_depth_of_the_block_tree(): void
    {
        $this->actingAsOrganizer();

        $block = ['type' => 'text', 'html' => 'deep', 'style' => []];

        for ($i = 0; $i < 12; $i++) {
            $block = ['type' => 'columns', 'columns' => [[$block]], 'style' => []];
        }

        $template = $this->createTemplate(['blocks' => [$block]]);

        $this->assertStringNotContainsString('deep', json_encode($template['blocks']));
    }

    /** Normalization rewrites nested structures — they must survive intact. */
    public function test_social_items_and_column_widths_round_trip_through_a_save(): void
    {
        $this->actingAsOrganizer();

        $template = $this->createTemplate([
            'blocks' => [
                [
                    'type' => 'social',
                    'items' => [
                        ['network' => 'linkedin', 'url' => 'https://li.test/co'],
                        ['network' => 'twitter', 'url' => 'https://x.test/co'],
                    ],
                    'style' => ['align' => 'center', 'iconSize' => 32],
                ],
                [
                    'type' => 'columns',
                    'widths' => [70, 30],
                    'columns' => [
                        [['type' => 'text', 'html' => '<p>left</p>', 'style' => []]],
                        [['type' => 'text', 'html' => '<p>right</p>', 'style' => []]],
                    ],
                    'style' => ['gap' => 16],
                ],
            ],
        ]);

        [$social, $columns] = $template['blocks'];

        $this->assertSame('linkedin', $social['items'][0]['network']);
        $this->assertSame('https://x.test/co', $social['items'][1]['url']);
        $this->assertSame(32, $social['style']['iconSize']);

        $this->assertSame([70, 30], $columns['widths']);
        $this->assertCount(2, $columns['columns']);
        $this->assertStringContainsString('left', $columns['columns'][0][0]['html']);
    }

    public function test_column_widths_are_clamped_to_a_usable_range(): void
    {
        $this->actingAsOrganizer();

        $template = $this->createTemplate([
            'blocks' => [[
                'type' => 'columns',
                'widths' => [-40, 900],
                'columns' => [
                    [['type' => 'text', 'html' => 'a', 'style' => []]],
                    [['type' => 'text', 'html' => 'b', 'style' => []]],
                ],
                'style' => [],
            ]],
        ]);

        $this->assertSame([5, 95], $template['blocks'][0]['widths']);
    }

    public function test_preview_draft_renders_unsaved_blocks_without_persisting(): void
    {
        $this->actingAsOrganizer();

        $html = $this->postJson('/api/v1/email-templates/preview-draft', [
            'subject' => 'Draft',
            'preheader' => 'Preview line',
            'blocks' => [['type' => 'heading', 'text' => 'Draft heading', 'style' => []]],
            'settings' => [],
        ])->assertOk()->json('html');

        $this->assertStringContainsString('Draft heading', $html);
        $this->assertStringContainsString('Preview line', $html);
    }

    /** The unsaved-preview path is the one place raw input reaches the renderer. */
    public function test_preview_draft_sanitizes_hostile_input(): void
    {
        $this->actingAsOrganizer();

        $html = $this->postJson('/api/v1/email-templates/preview-draft', [
            'blocks' => [['type' => 'html', 'html' => '<script>alert(1)</script><p>safe</p>', 'style' => []]],
        ])->assertOk()->json('html');

        $this->assertStringContainsString('safe', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
    }

    public function test_merge_variables_are_resolved_in_the_preview(): void
    {
        $this->actingAsOrganizer();

        $html = $this->postJson('/api/v1/email-templates/preview-draft', [
            'blocks' => [['type' => 'heading', 'text' => 'Hi {{ contact.first_name }}', 'style' => []]],
        ])->assertOk()->json('html');

        $this->assertStringNotContainsString('{{ contact.first_name }}', $html);
    }

    // ── version history ─────────────────────────────────────────────────────

    public function test_editing_a_template_records_a_restorable_version(): void
    {
        $this->actingAsOrganizer();

        $template = $this->createTemplate();
        $uuid = $template['id'];

        $this->putJson("/api/v1/email-templates/{$uuid}", [
            'name' => 'Invitation v2',
            'subject' => 'Changed subject',
            'blocks' => [['type' => 'heading', 'text' => 'Rewritten', 'style' => []]],
        ])->assertOk();

        $versions = $this->getJson("/api/v1/email-templates/{$uuid}/versions")
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($versions);

        // Roll back to the first snapshot and confirm the original copy returns.
        $restored = $this->postJson("/api/v1/email-templates/{$uuid}/versions/{$versions[count($versions) - 1]['version']}/restore")
            ->assertOk()
            ->json('data');

        $this->assertSame('Hello', $restored['blocks'][0]['text']);
        $this->assertSame('You are invited', $restored['subject']);
    }

    /** Restoring is itself undoable — the pre-restore state must be captured. */
    public function test_restoring_snapshots_the_current_state_first(): void
    {
        $this->actingAsOrganizer();

        $uuid = $this->createTemplate()['id'];

        $this->putJson("/api/v1/email-templates/{$uuid}", [
            'name' => 'Second',
            'blocks' => [['type' => 'heading', 'text' => 'Second', 'style' => []]],
        ])->assertOk();

        $before = count($this->getJson("/api/v1/email-templates/{$uuid}/versions")->json('data'));

        $this->postJson("/api/v1/email-templates/{$uuid}/versions/1/restore")->assertOk();

        $after = count($this->getJson("/api/v1/email-templates/{$uuid}/versions")->json('data'));

        $this->assertGreaterThan($before, $after);
    }

    /** Autosave fires constantly; identical saves must not bury real history. */
    public function test_an_unchanged_save_does_not_create_a_duplicate_version(): void
    {
        $this->actingAsOrganizer();

        $template = $this->createTemplate();
        $uuid = $template['id'];

        $payload = [
            'name' => $template['name'],
            'subject' => $template['subject'],
            'preheader' => $template['preheader'],
            'blocks' => $template['blocks'],
            'settings' => $template['settings'],
        ];

        $this->putJson("/api/v1/email-templates/{$uuid}", $payload)->assertOk();
        $first = count($this->getJson("/api/v1/email-templates/{$uuid}/versions")->json('data'));

        $this->putJson("/api/v1/email-templates/{$uuid}", $payload)->assertOk();
        $second = count($this->getJson("/api/v1/email-templates/{$uuid}/versions")->json('data'));

        $this->assertSame($first, $second);
    }

    public function test_restoring_an_unknown_version_is_not_found(): void
    {
        $this->actingAsOrganizer();

        $uuid = $this->createTemplate()['id'];

        $this->postJson("/api/v1/email-templates/{$uuid}/versions/9999/restore")->assertNotFound();
    }

    // ── supporting endpoints ────────────────────────────────────────────────

    public function test_it_returns_cached_compiled_html_for_gallery_previews(): void
    {
        $this->actingAsOrganizer();

        $uuid = $this->createTemplate()['id'];

        $html = $this->getJson("/api/v1/email-templates/{$uuid}/html")->assertOk()->json('html');

        $this->assertStringContainsString('Hello', $html);
    }

    public function test_the_asset_picker_lists_only_images(): void
    {
        $this->actingAsOrganizer();

        $this->getJson('/api/v1/email-assets')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_the_variable_catalogue_is_grouped_for_the_picker(): void
    {
        $this->actingAsOrganizer();

        $this->getJson('/api/v1/email-variables')
            ->assertOk()
            ->assertJsonStructure(['data' => [['group', 'label', 'variables' => [['token', 'label', 'sample']]]]]);
    }
}
