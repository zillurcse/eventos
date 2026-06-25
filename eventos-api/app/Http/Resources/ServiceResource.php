<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Represents a "service" — a group of `service_items` (options) that share a
 * group_uuid. The resource is constructed from the group's lead item, which
 * must have its sibling options attached as `->group_options` (a Collection).
 */
class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Collection $options */
        $options = $this->group_options;
        $lead = $options->first();

        return [
            'group_uuid' => $lead->group_uuid,
            'category' => [
                'id' => $lead->category_id,
                'name' => $this->whenLoaded('category', fn () => $lead->category?->name) ?? $lead->category?->name,
            ],
            'currency' => $lead->currency,
            'tax' => (float) $lead->tax,
            'enable_discount' => (bool) $lead->enable_discount,
            'discount' => (float) $lead->discount,
            'discount_type' => $lead->discount_type,
            'discount_start_date' => optional($lead->discount_start_date)->toDateString(),
            'discount_end_date' => optional($lead->discount_end_date)->toDateString(),
            'description' => $lead->description,
            'long_description' => $lead->long_description,
            'dynamic_pricing' => (bool) $lead->dynamic_pricing,
            'rate_conditions' => $lead->rate_conditions ?? [],
            'is_active' => (bool) $lead->is_active,
            'status' => $lead->status,
            'options' => $options->map(fn ($it) => [
                'id' => $it->id,
                'uuid' => $it->uuid,
                'name' => $it->title,
                'unit' => $it->unit,
                'rate' => (float) $it->rate,
                'image' => $it->image,
            ])->values(),
            // table conveniences
            'title' => $lead->title,
            'unit' => $lead->unit,
            'rate' => (float) $lead->rate,
            'more_count' => max(0, $options->count() - 1),
        ];
    }
}
