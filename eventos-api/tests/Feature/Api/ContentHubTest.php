<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Content Hub & Communication: blog posts, gallery, CTAs, gamification, services. */
class ContentHubTest extends TestCase
{
    use DatabaseTransactions;

    public function test_blog_post_crud(): void
    {
        $this->actingAsOrganizer();
        $event = $this->createEvent();
        $base = "/api/v1/events/{$event['id']}/blog-posts";

        $postUuid = $this->postJson($base, [
            'title' => 'Welcome to the Event',
            'excerpt' => 'See you there',
            'body' => 'Full article body.',
            'status' => 'published',
        ])->assertCreated()->json('data.id');

        $this->getJson($base)->assertOk()->assertJsonStructure(['data']);

        $this->patchJson("{$base}/{$postUuid}", ['title' => 'Welcome (Edited)'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Welcome (Edited)');

        $this->deleteJson("{$base}/{$postUuid}")
            ->assertOk()
            ->assertJsonPath('message', 'Blog post deleted.');
    }

    public function test_gallery_images_can_be_added(): void
    {
        $this->actingAsOrganizer();
        $event = $this->createEvent();
        $base = "/api/v1/events/{$event['id']}/gallery";

        $this->postJson($base, [
            'images' => [
                ['url' => 'https://example.test/a.jpg', 'caption' => 'A'],
                ['url' => 'https://example.test/b.jpg', 'caption' => 'B'],
            ],
        ])->assertOk()->assertJsonStructure(['data']);

        $this->getJson($base)->assertOk();
    }

    public function test_cta_crud(): void
    {
        $this->actingAsOrganizer();
        $event = $this->createEvent();
        $base = "/api/v1/events/{$event['id']}/ctas";

        $ctaId = $this->postJson($base, [
            'type' => 'text',
            'title' => 'Visit our booth',
            'description' => 'Booth 12',
        ])->assertCreated()->json('data.id');

        $this->getJson($base)->assertOk()->assertJsonStructure(['data']);

        $this->deleteJson("{$base}/{$ctaId}")
            ->assertOk()
            ->assertJsonPath('message', 'CTA deleted.');
    }

    public function test_gamification_config_can_be_read_and_updated(): void
    {
        $this->actingAsOrganizer();
        $event = $this->createEvent();
        $base = "/api/v1/events/{$event['id']}/gamification";

        $this->getJson($base)->assertOk()->assertJsonStructure(['data']);

        $this->putJson($base, [
            'enabled' => true,
            'award_title' => 'Top Networker',
        ])->assertOk()->assertJsonStructure(['data']);
    }

    public function test_service_category_and_service_flow(): void
    {
        $this->actingAsOrganizer();
        $event = $this->createEvent();
        $eventUuid = $event['id'];

        $categoryId = $this->postJson("/api/v1/events/{$eventUuid}/service-categories", [
            'name' => 'Catering',
        ])->assertCreated()->json('data.id');

        $this->getJson("/api/v1/events/{$eventUuid}/service-categories")
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->postJson("/api/v1/events/{$eventUuid}/services", [
            'category_id' => $categoryId,
            'currency' => 'USD',
            'description' => 'Lunch packages',
            'options' => [
                ['name' => 'Standard Lunch', 'unit' => 'person', 'rate' => 25],
                ['name' => 'Premium Lunch', 'unit' => 'person', 'rate' => 45],
            ],
        ])->assertCreated()->assertJsonStructure(['data']);

        $this->getJson("/api/v1/events/{$eventUuid}/services")
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_service_requires_options(): void
    {
        $this->actingAsOrganizer();
        $event = $this->createEvent();

        $this->postJson("/api/v1/events/{$event['id']}/services", ['category_id' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['options']);
    }
}
