<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Exhibitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Exhibitor projects (§6.3). Scoped through the parent exhibitor, which is
 * resolved under the tenant GUC, so only same-org exhibitors are reachable.
 */
class ExhibitorProjectController extends Controller
{
    public function index(string $exhibitorUuid): JsonResponse
    {
        $exhibitor = Exhibitor::where('uuid', $exhibitorUuid)->firstOrFail();

        return response()->json(['data' => $exhibitor->projects()->latest('id')->get()
            ->map(fn ($p) => $this->present($p))->values()]);
    }

    public function store(string $exhibitorUuid, Request $request): JsonResponse
    {
        $exhibitor = Exhibitor::where('uuid', $exhibitorUuid)->firstOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'string', 'max:30'],
        ]);

        $project = $exhibitor->projects()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? null,
        ]);

        return response()->json(['data' => $this->present($project)], 201);
    }

    public function destroy(string $exhibitorUuid, int $project): JsonResponse
    {
        $exhibitor = Exhibitor::where('uuid', $exhibitorUuid)->firstOrFail();

        $exhibitor->projects()->whereKey($project)->firstOrFail()->delete();

        return response()->json(['message' => 'Project removed.']);
    }

    protected function present($p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'description' => $p->description,
            'status' => $p->status,
        ];
    }
}
