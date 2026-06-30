<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Exhibitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Exhibitor documents (§6.3). Scoped through the parent exhibitor, which is
 * resolved under the tenant GUC, so only same-org exhibitors are reachable.
 */
class ExhibitorDocumentController extends Controller
{
    public function index(string $exhibitorUuid): JsonResponse
    {
        $exhibitor = Exhibitor::where('uuid', $exhibitorUuid)->firstOrFail();

        return response()->json(['data' => $exhibitor->documents()->latest('id')->get()
            ->map(fn ($d) => $this->present($d))->values()]);
    }

    public function store(string $exhibitorUuid, Request $request): JsonResponse
    {
        $exhibitor = Exhibitor::where('uuid', $exhibitorUuid)->firstOrFail();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'url' => ['nullable', 'string', 'max:500'],
            'file_id' => ['nullable', 'integer'],
            'visibility' => ['nullable', 'in:all,members,private'],
        ]);

        $doc = $exhibitor->documents()->create([
            'title' => $data['title'],
            'url' => $data['url'] ?? null,
            'file_id' => $data['file_id'] ?? null,
            'visibility' => $data['visibility'] ?? 'all',
        ]);

        return response()->json(['data' => $this->present($doc)], 201);
    }

    public function destroy(string $exhibitorUuid, int $document): JsonResponse
    {
        $exhibitor = Exhibitor::where('uuid', $exhibitorUuid)->firstOrFail();

        $exhibitor->documents()->whereKey($document)->firstOrFail()->delete();

        return response()->json(['message' => 'Document removed.']);
    }

    protected function present($d): array
    {
        return [
            'id' => $d->id,
            'title' => $d->title,
            'url' => $d->url,
            'visibility' => $d->visibility,
        ];
    }
}
