<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

/**
 * Plan catalog management (architecture §2.1). Plans are platform-level (no RLS).
 */
class AdminPlanController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return PlanResource::collection(Plan::with('features')->orderBy('sort_order')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'price_cents' => ['nullable', 'integer', 'min:0'],
            'billing_interval' => ['nullable', 'in:month,year,custom'],
            'trial_days' => ['nullable', 'integer', 'min:0'],
            'limits' => ['nullable', 'array'],
            'is_public' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $plan = Plan::create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
            'price_cents' => $data['price_cents'] ?? 0,
            'currency' => 'USD',
            'billing_interval' => $data['billing_interval'] ?? 'month',
            'trial_days' => $data['trial_days'] ?? 0,
            'limits' => $data['limits'] ?? null,
            'is_public' => $data['is_public'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return response()->json(['data' => new PlanResource($plan)], 201);
    }

    public function update(string $uuid, Request $request): JsonResponse
    {
        $plan = Plan::where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'price_cents' => ['sometimes', 'integer', 'min:0'],
            'trial_days' => ['sometimes', 'integer', 'min:0'],
            'limits' => ['sometimes', 'array'],
            'is_public' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer'],
        ]);

        $plan->update($data);

        return response()->json(['data' => new PlanResource($plan->fresh('features'))]);
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'plan';
        $slug = $base;
        $i = 1;
        while (Plan::where('slug', $slug)->withTrashed()->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
