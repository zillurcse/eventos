<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BadgeDesignResource;
use App\Models\BadgeDesign;
use App\Models\Event;
use App\Services\Badges\BadgeRenderData;
use App\Services\Badges\BadgeTemplateFactory;
use App\Support\BadgeAudience;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Badge designs for the badge.expouse canvas editor. Event-scoped on
 * index/store/element-library; id-based on show/update/destroy (resolved here,
 * not via route binding, so the tenant GUC is already set and RLS doesn't hide
 * the row at bind time). JSON blobs are read with $request->input() because
 * $request->validate() strips nested keys.
 */
class BadgeDesignController extends Controller
{
    /** JSON canvas-state fields. */
    private const JSON_FIELDS = ['badge_json', 'font_json', 'back_json', 'layers', 'meta'];

    /** Scalar fields the editor may persist alongside the canvas JSON. */
    private const SCALAR_FIELDS = [
        'name', 'format', 'is_default', 'measurements_type', 'width', 'height',
        'bg_color', 'bg_image', 'padding_top', 'padding_right', 'padding_bottom',
        'padding_left', 'badge_for',
    ];

    public function index(string $uuid): AnonymousResourceCollection
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $designs = BadgeDesign::where('event_id', $event->id)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        return BadgeDesignResource::collection($designs);
    }

    public function store(Request $request, string $uuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $request->validate($this->rules(required: true));

        $design = BadgeDesign::create([
            'event_id' => $event->id,
            'name' => $request->input('name'),
            'badge_for' => $request->input('badge_for'),
            'format' => $request->input('format'),
            'is_default' => $request->boolean('is_default'),
            'measurements_type' => $request->input('measurements_type'),
            'width' => $request->input('width'),
            'height' => $request->input('height'),
            'bg_color' => $request->input('bg_color'),
            'bg_image' => $request->input('bg_image'),
            'padding_top' => $request->input('padding_top'),
            'padding_right' => $request->input('padding_right'),
            'padding_bottom' => $request->input('padding_bottom'),
            'padding_left' => $request->input('padding_left'),
            'badge_json' => $request->input('badge_json', []),
            'font_json' => $request->input('font_json'),
            'back_json' => $request->input('back_json'),
            'layers' => $request->input('layers', []),
            'meta' => $request->input('meta'),
            'created_by' => $request->user()?->id,
        ]);

        if ($design->is_default) {
            $this->clearOtherDefaults($design);
        }

        return response()->json(['data' => new BadgeDesignResource($design)], 201);
    }

    public function show(int $badgeDesign): JsonResponse
    {
        $model = BadgeDesign::findOrFail($badgeDesign);

        return response()->json(['data' => new BadgeDesignResource($model)]);
    }

    public function update(Request $request, int $badgeDesign): JsonResponse
    {
        $model = BadgeDesign::findOrFail($badgeDesign);

        $request->validate($this->rules(required: false));

        foreach ([...self::SCALAR_FIELDS, ...self::JSON_FIELDS] as $key) {
            if ($request->has($key)) {
                $model->{$key} = $key === 'is_default'
                    ? $request->boolean($key)
                    : $request->input($key);
            }
        }
        $model->updated_by = $request->user()?->id;
        $model->save();

        // Only one design per event can be the default one.
        if ($request->has('is_default') && $model->is_default) {
            $this->clearOtherDefaults($model);
        }

        return response()->json(['data' => new BadgeDesignResource($model)]);
    }

    public function destroy(int $badgeDesign): JsonResponse
    {
        BadgeDesign::findOrFail($badgeDesign)->delete();

        return response()->json(['status' => 'success']);
    }

    /**
     * The draggable element catalogue the editor's Sidebar renders — the shape
     * Sidebar.vue consumes: { data: { designGroups, openGroups } }.
     *
     * Each item's `key` is copied onto the dropped box by useCanvasStore, and
     * that key is the *only* thing that makes a badge dynamic: at render time a
     * box whose key is a BadgeRenderData token is drawn with that person's value
     * instead of the authored placeholder. So the merge-field groups below must
     * stay in step with BadgeRenderData::KEYS — a key offered here that the
     * service never produces would silently print its placeholder on real card
     * stock. The `elements` group is the opposite: static furniture, keyed by
     * its own type so it never collides with a token.
     */
    public function elementLibrary(string $uuid): JsonResponse
    {
        Event::where('uuid', $uuid)->firstOrFail();

        $designGroups = [
            [
                'type' => 'attendee_info',
                'label' => 'Attendee Information',
                'icon' => 'mdi:account-details',
                'items' => [
                    ['type' => 'h1', 'key' => 'full_name', 'label' => 'Full Name', 'value' => 'Full Name', 'icon' => 'mdi:account'],
                    ['type' => 'p', 'key' => 'first_name', 'label' => 'First Name', 'value' => 'First Name', 'icon' => 'mdi:account-outline'],
                    ['type' => 'p', 'key' => 'last_name', 'label' => 'Last Name', 'value' => 'Last Name', 'icon' => 'mdi:account-outline'],
                    ['type' => 'p', 'key' => 'designation', 'label' => 'Designation', 'value' => 'Designation', 'icon' => 'mdi:badge-account'],
                    ['type' => 'p', 'key' => 'company', 'label' => 'Company', 'value' => 'Company', 'icon' => 'mdi:office-building'],
                    ['type' => 'p', 'key' => 'country', 'label' => 'Country', 'value' => 'Country', 'icon' => 'mdi:earth'],
                    ['type' => 'p', 'key' => 'email', 'label' => 'Email', 'value' => 'email@example.com', 'icon' => 'mdi:email'],
                    ['type' => 'p', 'key' => 'phone', 'label' => 'Phone', 'value' => '+00 000 000 000', 'icon' => 'mdi:phone'],
                    ['type' => 'avatar', 'key' => 'avatar', 'label' => 'Photo', 'value' => 'Photo', 'icon' => 'mdi:account-circle'],
                    // What the badge calls this person: their audience label, or
                    // the guest sub-type ("Media") on a guest badge.
                    ['type' => 'p', 'key' => 'role_label', 'label' => 'Badge Type', 'value' => 'Attendee', 'icon' => 'mdi:card-account-details'],
                    ['type' => 'p', 'key' => 'guest_type', 'label' => 'Guest Type', 'value' => 'Media', 'icon' => 'mdi:star-circle'],
                ],
            ],
            [
                'type' => 'event_info',
                'label' => 'Event Information',
                'icon' => 'mdi:calendar-star',
                'items' => [
                    ['type' => 'h1', 'key' => 'event_name', 'label' => 'Event Name', 'value' => 'Event Name', 'icon' => 'mdi:calendar-text'],
                    ['type' => 'avatar', 'key' => 'event_logo', 'label' => 'Event Logo', 'value' => 'Event Logo', 'icon' => 'mdi:image-filter-center-focus'],
                    ['type' => 'p', 'key' => 'event_dates', 'label' => 'Event Dates', 'value' => '12 – 14 Mar 2026', 'icon' => 'mdi:calendar-range'],
                    ['type' => 'p', 'key' => 'event_venue', 'label' => 'Venue', 'value' => 'Venue', 'icon' => 'mdi:map-marker'],
                    ['type' => 'p', 'key' => 'event_city', 'label' => 'City', 'value' => 'City', 'icon' => 'mdi:city'],
                ],
            ],
            [
                'type' => 'elements',
                'label' => 'Elements',
                'icon' => 'mdi:shape',
                'items' => [
                    ['type' => 'h1', 'key' => 'h1', 'label' => 'Heading', 'value' => 'Heading', 'icon' => 'mdi:format-header-1'],
                    ['type' => 'p', 'key' => 'p', 'label' => 'Text', 'value' => 'Sample Text', 'icon' => 'mdi:format-text'],
                    // The QR always encodes the participation uuid, whatever the
                    // authored text says — see BadgeRenderData.
                    ['type' => 'qrcode', 'key' => 'qrcode', 'label' => 'QR Code', 'value' => 'uuid', 'icon' => 'mdi:qrcode'],
                    ['type' => 'img', 'key' => 'img', 'label' => 'Image', 'value' => 'Image', 'icon' => 'mdi:image'],
                ],
            ],
            [
                'type' => 'background',
                'label' => 'Background',
                'icon' => 'mdi:image-area',
                'items' => [
                    ['type' => 'background', 'key' => 'background_img', 'label' => 'Background Image', 'value' => 'background', 'icon' => 'mdi:image-area'],
                    ['type' => 'gradient', 'key' => 'gradient', 'label' => 'Gradient', 'value' => 'gradient', 'icon' => 'mdi:gradient-horizontal'],
                    ['type' => 'color', 'key' => 'color', 'label' => 'Solid Color', 'value' => 'color', 'icon' => 'mdi:palette'],
                    ['type' => 'none', 'key' => 'none', 'label' => 'Remove Background', 'value' => 'none', 'icon' => 'mdi:close-circle'],
                ],
            ],
            [
                'type' => 'punching_area',
                'label' => 'Punching Area',
                'icon' => 'mdi:checkbox-blank-circle-outline',
                'items' => [
                    ['type' => 'circle-center', 'key' => 'circle-center', 'label' => 'Circle Center', 'value' => 'circle-center', 'icon' => 'mdi:circle-outline'],
                    ['type' => 'circle-left-right', 'key' => 'circle-left-right', 'label' => 'Circle Left/Right', 'value' => 'circle-left-right', 'icon' => 'mdi:circle-outline'],
                    ['type' => 'long-center', 'key' => 'long-center', 'label' => 'Long Center', 'value' => 'long-center', 'icon' => 'mdi:rectangle-outline'],
                    ['type' => 'long-left-right', 'key' => 'long-left-right', 'label' => 'Long Left/Right', 'value' => 'long-left-right', 'icon' => 'mdi:rectangle-outline'],
                ],
            ],
        ];

        return response()->json([
            'data' => [
                'designGroups' => $designGroups,
                'openGroups' => [
                    'attendee_info' => true,
                    'event_info' => false,
                    'elements' => true,
                    'background' => false,
                    'punching_area' => false,
                ],
            ],
        ]);
    }

    /**
     * Create the starter badge for every audience this event does not have one
     * for yet — attendee, speaker, exhibitor, sponsor. Idempotent: run it twice
     * and the second run reports zero created rather than duplicating designs,
     * which matters because the templates page offers it from an empty state
     * that a double-click can fire twice.
     */
    public function seedDefaults(Request $request, string $uuid, BadgeTemplateFactory $factory): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $existing = BadgeDesign::where('event_id', $event->id)
            ->pluck('badge_for')
            ->map(fn ($v) => BadgeAudience::tryNormalize($v))
            ->filter()
            ->map(fn (BadgeAudience $a) => $a->value);

        $created = [];

        foreach ([BadgeAudience::Attendee, BadgeAudience::Speaker, BadgeAudience::Exhibitor, BadgeAudience::Sponsor] as $audience) {
            if ($existing->contains($audience->value)) {
                continue;
            }

            $created[] = BadgeDesign::create([
                ...$factory->build($audience),
                'event_id' => $event->id,
                // The attendee badge is the fallback for anyone whose audience
                // has no design — the largest group, so the safest default.
                'is_default' => $audience === BadgeAudience::Attendee
                    && ! BadgeDesign::where('event_id', $event->id)->where('is_default', true)->exists(),
                'created_by' => $request->user()?->id,
            ]);
        }

        return response()->json([
            'data' => BadgeDesignResource::collection($created),
            'meta' => ['created' => count($created)],
        ], 201);
    }

    /**
     * Placeholder values for previewing a design with nobody behind it. The
     * admin merges these into the design client-side, so what an organizer sees
     * on the templates page is drawn by the same code path as a real badge.
     */
    public function sampleData(string $uuid, Request $request, BadgeRenderData $data): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        return response()->json([
            'data' => $data->sample(
                $event,
                BadgeAudience::tryNormalize($request->query('badge_for')),
                $request->query('guest_type'),
            ),
        ]);
    }

    /** Demote every other design of the same event. */
    private function clearOtherDefaults(BadgeDesign $design): void
    {
        BadgeDesign::where('event_id', $design->event_id)
            ->where('id', '!=', $design->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    private function rules(bool $required): array
    {
        return [
            'name' => [$required ? 'required' : 'sometimes', 'string', 'max:255'],
            // The audience vocabulary, not the participation role vocabulary.
            'badge_for' => ['nullable', 'string', Rule::in(BadgeAudience::values())],
            'meta' => ['nullable', 'array'],
            'meta.guest_type' => ['nullable', 'string', 'max:60'],
            'format' => ['nullable', 'string', 'max:255'],
            'is_default' => ['nullable', 'boolean'],
            'measurements_type' => ['nullable', 'string', 'max:255'],
            'width' => ['nullable', 'string', 'max:255'],
            'height' => ['nullable', 'string', 'max:255'],
            'bg_color' => ['nullable', 'string', 'max:255'],
            'bg_image' => ['nullable', 'string', 'max:255'],
            'padding_top' => ['nullable', 'string', 'max:255'],
            'padding_right' => ['nullable', 'string', 'max:255'],
            'padding_bottom' => ['nullable', 'string', 'max:255'],
            'padding_left' => ['nullable', 'string', 'max:255'],
            'badge_json' => ['nullable', 'array'],
            'font_json' => ['nullable', 'array'],
            'back_json' => ['nullable', 'array'],
            'layers' => ['nullable', 'array'],
        ];
    }
}
