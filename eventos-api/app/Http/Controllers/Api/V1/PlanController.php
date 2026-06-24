<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlanController extends Controller
{
    /** Public pricing catalog. */
    public function index(): AnonymousResourceCollection
    {
        return PlanResource::collection(
            Plan::where('is_public', true)->with('features')->orderBy('sort_order')->get()
        );
    }
}
