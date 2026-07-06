<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\IconResource;
use App\Models\Icon;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class IconController extends Controller
{
    /** Global icon catalog for icon-picker fields (e.g. Participate Profile). */
    public function index(): AnonymousResourceCollection
    {
        return IconResource::collection(
            Icon::orderBy('sort_order')->orderBy('label')->get()
        );
    }
}
