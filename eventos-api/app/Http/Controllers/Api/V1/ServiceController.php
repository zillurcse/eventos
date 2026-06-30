<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Models\Event;
use App\Models\ServiceCategory;
use App\Models\ServiceItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Service catalogue (§ EXPOUSE "Services"). A "service" is a group of options
 * (service_items) sharing a group_uuid + common category/currency/tax/discount.
 */
class ServiceController extends Controller
{
    public function index(string $uuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $items = ServiceItem::with('category')
            ->where('event_id', $event->id)
            ->orderBy('id')
            ->get();

        $groups = $items->groupBy('group_uuid')
            ->map(function (Collection $group) {
                $lead = $group->first();      // first-added option leads the row
                $lead->group_options = $group;

                return $lead;
            })
            ->sortByDesc('id')                // groups newest-first by lead id
            ->map(fn ($lead) => new ServiceResource($lead))
            ->values();

        return response()->json(['data' => $groups]);
    }

    public function store(Request $request, string $uuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();
        $data = $this->validateService($request);
        $category = $this->resolveCategory($event, $data['category_id']);

        $groupUuid = (string) Str::uuid();

        DB::transaction(function () use ($event, $category, $data, $groupUuid) {
            foreach ($data['options'] as $option) {
                $this->createOption($event, $category, $data, $option, $groupUuid);
            }
        });

        return $this->groupResponse($event, $groupUuid, 201);
    }

    public function update(Request $request, string $group): JsonResponse
    {
        $existing = ServiceItem::where('group_uuid', $group)->get();
        abort_if($existing->isEmpty(), 404);

        $event = Event::findOrFail($existing->first()->event_id);
        $data = $this->validateService($request);
        $category = $this->resolveCategory($event, $data['category_id']);

        DB::transaction(function () use ($event, $category, $data, $group, $existing) {
            $incomingIds = collect($data['options'])->pluck('id')->filter()->all();

            // Remove options dropped in the editor.
            $existing->whereNotIn('id', $incomingIds)->each->delete();

            foreach ($data['options'] as $option) {
                $item = isset($option['id'])
                    ? $existing->firstWhere('id', $option['id'])
                    : null;

                if ($item) {
                    $item->update($this->optionAttributes($category, $data, $option));
                } else {
                    $this->createOption($event, $category, $data, $option, $group);
                }
            }
        });

        return $this->groupResponse($event, $group);
    }

    public function destroy(string $group): JsonResponse
    {
        $items = ServiceItem::where('group_uuid', $group)->get();
        abort_if($items->isEmpty(), 404);

        $items->each->delete();

        return response()->json(['status' => 'success']);
    }

    // ── helpers ────────────────────────────────────────────────────────────

    private function validateService(Request $request): array
    {
        return $request->validate([
            'category_id' => ['required', 'integer'],
            'currency' => ['nullable', 'string', 'size:3'],
            'description' => ['nullable', 'string'],
            'long_description' => ['nullable', 'string'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'enable_discount' => ['nullable', 'boolean'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', 'in:fixed,percentage'],
            'discount_start_date' => ['nullable', 'date'],
            'discount_end_date' => ['nullable', 'date'],
            'dynamic_pricing' => ['nullable', 'boolean'],
            'rate_conditions' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
            'options' => ['required', 'array', 'min:1'],
            'options.*.id' => ['nullable', 'integer'],
            'options.*.name' => ['required', 'string', 'max:255'],
            'options.*.unit' => ['nullable', 'string', 'max:60'],
            'options.*.rate' => ['required', 'numeric', 'min:0'],
            'options.*.image' => ['nullable', 'string', 'max:500'],
        ]);
    }

    /** Resolve a category and confirm it belongs to this event (RLS scopes to org). */
    private function resolveCategory(Event $event, int $categoryId): ServiceCategory
    {
        return ServiceCategory::where('id', $categoryId)
            ->where('event_id', $event->id)
            ->firstOrFail();
    }

    private function optionAttributes(ServiceCategory $category, array $data, array $option): array
    {
        return [
            'category_id' => $category->id,
            'title' => $option['name'],
            'unit' => $option['unit'] ?? null,
            'rate' => $option['rate'],
            'image' => $option['image'] ?? null,
            'currency' => $data['currency'] ?? 'USD',
            'description' => $data['description'] ?? null,
            'long_description' => $data['long_description'] ?? null,
            'tax' => $data['tax'] ?? 0,
            'enable_discount' => $data['enable_discount'] ?? false,
            'discount' => $data['discount'] ?? 0,
            'discount_type' => $data['discount_type'] ?? 'fixed',
            'discount_start_date' => $data['discount_start_date'] ?? null,
            'discount_end_date' => $data['discount_end_date'] ?? null,
            'dynamic_pricing' => $data['dynamic_pricing'] ?? false,
            'rate_conditions' => $data['rate_conditions'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ];
    }

    private function createOption(Event $event, ServiceCategory $category, array $data, array $option, string $groupUuid): ServiceItem
    {
        return ServiceItem::create(array_merge($this->optionAttributes($category, $data, $option), [
            'uuid' => (string) Str::uuid(),
            'group_uuid' => $groupUuid,
            'event_id' => $event->id,
            'status' => 'pending',
        ]));
    }

    private function groupResponse(Event $event, string $groupUuid, int $status = 200): JsonResponse
    {
        $group = ServiceItem::with('category')
            ->where('group_uuid', $groupUuid)
            ->orderBy('id')
            ->get();

        $lead = $group->first();
        $lead->group_options = $group;

        return response()->json(['data' => new ServiceResource($lead)], $status);
    }
}
