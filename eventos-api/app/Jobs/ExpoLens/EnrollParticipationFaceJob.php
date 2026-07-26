<?php

namespace App\Jobs\ExpoLens;

use App\Models\File;
use App\Models\Participation;
use App\Services\ExpoLens\FaceService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class EnrollParticipationFaceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 240;

    public function __construct(
        public int $organizationId,
        public int $eventId,
        public int $participationId,
        public int $fileId,
        public string $source = 'avatar',
    ) {
        $this->onQueue('media');
    }

    public function handle(FaceService $faceService, TenantContext $tenant): void
    {
        $tenant->set($this->organizationId);
        DB::statement("set app.current_organization = '{$this->organizationId}'");

        $participation = Participation::where('event_id', $this->eventId)->findOrFail($this->participationId);
        $file = File::where('organization_id', $this->organizationId)->findOrFail($this->fileId);
        $result = $faceService->embed($file);
        $face = $result['face'] ?? null;

        if (! is_array($face) || count($face['embedding'] ?? []) !== 512) {
            throw new RuntimeException('Face service returned an invalid enrollment embedding.');
        }

        $consentedAt = data_get($participation->profile_data, 'expolens_consent_at');
        if (! $consentedAt) {
            throw new RuntimeException('ExpoLens biometric consent is required before enrollment.');
        }

        $vector = json_encode(array_map('floatval', $face['embedding']), JSON_THROW_ON_ERROR);
        $now = now();

        DB::statement(
            'INSERT INTO expolens_face_embeddings
                (event_id, organization_id, participation_id, source, model, dimensions,
                 quality_score, source_url, consented_at, enrolled_at, created_at, updated_at, embedding)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?::vector)
             ON CONFLICT (participation_id, source) DO UPDATE SET
                 model = EXCLUDED.model,
                 dimensions = EXCLUDED.dimensions,
                 quality_score = EXCLUDED.quality_score,
                 source_url = EXCLUDED.source_url,
                 consented_at = EXCLUDED.consented_at,
                 enrolled_at = EXCLUDED.enrolled_at,
                 updated_at = EXCLUDED.updated_at,
                 embedding = EXCLUDED.embedding',
            [
                $this->eventId,
                $this->organizationId,
                $this->participationId,
                $this->source,
                $result['model'] ?? 'buffalo_l',
                $result['dimensions'] ?? 512,
                $face['quality_score'] ?? null,
                Storage::disk($file->disk)->url($file->path),
                $consentedAt,
                $now,
                $now,
                $now,
                $vector,
            ],
        );
    }
}
