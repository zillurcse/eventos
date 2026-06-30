<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventAdResource;
use App\Models\Event;
use App\Models\EventAd;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Event advertisements (AD Managements). Ads are grouped by placement
 * (main | featured | content); up to MAX_PER_PLACEMENT per placement. Event-scoped
 * on index/store; id-based on show/update/destroy (resolved here so the tenant
 * GUC is set and RLS doesn't hide the row at bind time). JSON blobs read with
 * $request->input() since validate() strips nested keys.
 */
class EventAdController extends Controller
{
    private const PLACEMENTS = ['main', 'featured', 'content'];
    private const MAX_PER_PLACEMENT = 4;
    private const JSON_FIELDS = ['images', 'targeted_groups', 'targeted_pages'];

    public function index(string $uuid, Request $request): AnonymousResourceCollection
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $query = EventAd::where('event_id', $event->id)->orderBy('id');
        if ($request->filled('placement')) {
            $query->where('placement', $request->string('placement'));
        }

        return EventAdResource::collection($query->get());
    }

    public function store(Request $request, string $uuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $request->validate($this->rules(required: true));

        $placement = $request->input('placement');
        $count = EventAd::where('event_id', $event->id)->where('placement', $placement)->count();
        if ($count >= self::MAX_PER_PLACEMENT) {
            throw ValidationException::withMessages([
                'placement' => 'You can create up to '.self::MAX_PER_PLACEMENT.' ads for this placement.',
            ]);
        }

        $ad = EventAd::create([
            'event_id'        => $event->id,
            'placement'       => $placement,
            'title'           => $request->input('title'),
            'is_active'       => $request->boolean('is_active', true),
            'images'          => $request->input('images', []),
            'targeted_groups' => $request->input('targeted_groups', []),
            'targeted_pages'  => $request->input('targeted_pages', []),
            'start_at'        => $request->input('start_at'),
            'end_at'          => $request->input('end_at'),
            'created_by'      => $request->user()?->id,
        ]);

        return response()->json(['data' => new EventAdResource($ad)], 201);
    }

    public function show(int $ad): JsonResponse
    {
        return response()->json(['data' => new EventAdResource(EventAd::findOrFail($ad))]);
    }

    public function update(Request $request, int $ad): JsonResponse
    {
        $model = EventAd::findOrFail($ad);

        $request->validate($this->rules(required: false));

        foreach (['placement', 'title', 'start_at', 'end_at', ...self::JSON_FIELDS] as $key) {
            if ($request->has($key)) {
                $model->{$key} = $request->input($key);
            }
        }
        if ($request->has('is_active')) {
            $model->is_active = $request->boolean('is_active');
        }
        $model->updated_by = $request->user()?->id;
        $model->save();

        return response()->json(['data' => new EventAdResource($model)]);
    }

    public function destroy(int $ad): JsonResponse
    {
        EventAd::findOrFail($ad)->delete();

        return response()->json(['status' => 'success']);
    }

    /**
     * Ad performance for the Insights dashboard: per-event totals, a per-placement
     * breakdown and per-ad rows (impressions / clicks / CTR), built from the
     * denormalized counters on event_ads.
     */
    public function insights(string $uuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();
        $ads = EventAd::where('event_id', $event->id)->orderBy('id')->get();

        $ctr = fn (int $imp, int $clk) => $imp > 0 ? round($clk / $imp * 100, 1) : 0;

        $byPlacement = collect(self::PLACEMENTS)->map(function ($p) use ($ads, $ctr) {
            $group = $ads->where('placement', $p);
            $imp = (int) $group->sum('impressions');
            $clk = (int) $group->sum('clicks');

            return [
                'placement'   => $p,
                'ads'         => $group->count(),
                'active'      => $group->where('is_active', true)->count(),
                'impressions' => $imp,
                'clicks'      => $clk,
                'ctr'         => $ctr($imp, $clk),
            ];
        })->values();

        $totalImp = (int) $ads->sum('impressions');
        $totalClk = (int) $ads->sum('clicks');

        return response()->json([
            'data' => [
                'totals' => [
                    'ads'         => $ads->count(),
                    'active'      => $ads->where('is_active', true)->count(),
                    'impressions' => $totalImp,
                    'clicks'      => $totalClk,
                    'ctr'         => $ctr($totalImp, $totalClk),
                ],
                'by_placement' => $byPlacement,
                'ads' => $ads->map(fn (EventAd $a) => [
                    'id'          => $a->id,
                    'title'       => $a->title,
                    'placement'   => $a->placement,
                    'is_active'   => (bool) $a->is_active,
                    'image_url'   => $a->images[0]['image_url'] ?? null,
                    'impressions' => (int) $a->impressions,
                    'clicks'      => (int) $a->clicks,
                    'ctr'         => $ctr((int) $a->impressions, (int) $a->clicks),
                ])->values(),
            ],
        ]);
    }

    /** Record an impression or click against an ad (bumps the counter). */
    public function track(int $ad, Request $request): JsonResponse
    {
        $data = $request->validate(['type' => ['required', 'in:impression,click']]);

        $model = EventAd::findOrFail($ad);
        $model->increment($data['type'] === 'click' ? 'clicks' : 'impressions');

        return response()->json(['status' => 'success']);
    }

    private function rules(bool $required): array
    {
        return [
            'placement'              => [$required ? 'required' : 'sometimes', Rule::in(self::PLACEMENTS)],
            'title'                  => [$required ? 'required' : 'sometimes', 'string', 'max:255'],
            'is_active'              => ['nullable', 'boolean'],
            'images'                 => ['nullable', 'array', 'max:5'],
            'images.*.image_url'     => ['nullable', 'string', 'max:2000'],
            'images.*.redirect_type' => ['nullable', 'string', 'max:40'],
            'images.*.is_active'     => ['nullable', 'boolean'],
            'targeted_groups'        => ['nullable', 'array'],
            'targeted_groups.*'      => ['string', 'max:40'],
            'targeted_pages'         => ['nullable', 'array'],
            'targeted_pages.*'       => ['string', 'max:40'],
            'start_at'               => ['nullable', 'date'],
            'end_at'                 => ['nullable', 'date', 'after_or_equal:start_at'],
        ];
    }
}
