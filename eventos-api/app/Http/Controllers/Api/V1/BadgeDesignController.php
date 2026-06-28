<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BadgeDesignResource;
use App\Models\BadgeDesign;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
    private const JSON_FIELDS = ['badge_json', 'font_json', 'back_json', 'layers'];

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
            'created_by' => $request->user()?->id,
        ]);

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

        return response()->json(['data' => new BadgeDesignResource($model)]);
    }

    public function destroy(int $badgeDesign): JsonResponse
    {
        BadgeDesign::findOrFail($badgeDesign)->delete();

        return response()->json(['status' => 'success']);
    }

    /**
     * The draggable element catalogue the editor's Sidebar renders. Static for
     * now (a sensible default of attendee fields, elements and backgrounds); the
     * shape matches what Sidebar.vue consumes: { data: { designGroups, openGroups } }.
     * Event-scoped so it can later be derived from the event's registration form.
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
                    ['type' => 'p', 'key' => 'name', 'label' => 'Full Name', 'value' => 'Full Name', 'icon' => 'mdi:account'],
                    ['type' => 'p', 'key' => 'company', 'label' => 'Company', 'value' => 'Company', 'icon' => 'mdi:office-building'],
                    ['type' => 'p', 'key' => 'designation', 'label' => 'Designation', 'value' => 'Designation', 'icon' => 'mdi:badge-account'],
                    ['type' => 'p', 'key' => 'country', 'label' => 'Country', 'value' => 'Country', 'icon' => 'mdi:earth'],
                    ['type' => 'p', 'key' => 'email', 'label' => 'Email', 'value' => 'email@example.com', 'icon' => 'mdi:email'],
                ],
            ],
            [
                'type' => 'elements',
                'label' => 'Elements',
                'icon' => 'mdi:shape',
                'items' => [
                    ['type' => 'h1', 'key' => 'heading', 'label' => 'Heading', 'value' => 'Heading', 'icon' => 'mdi:format-header-1'],
                    ['type' => 'p', 'key' => 'text', 'label' => 'Text', 'value' => 'Sample Text', 'icon' => 'mdi:format-text'],
                    ['type' => 'qrcode', 'key' => 'qrcode', 'label' => 'QR Code', 'value' => 'QRCode', 'icon' => 'mdi:qrcode'],
                    ['type' => 'avatar', 'key' => 'avatar', 'label' => 'Avatar', 'value' => 'Avatar', 'icon' => 'mdi:account-circle'],
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
                    'elements' => true,
                    'background' => false,
                    'punching_area' => false,
                ],
            ],
        ]);
    }

    private function rules(bool $required): array
    {
        return [
            'name' => [$required ? 'required' : 'sometimes', 'string', 'max:255'],
            'badge_for' => ['nullable', 'string', 'max:255'],
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
