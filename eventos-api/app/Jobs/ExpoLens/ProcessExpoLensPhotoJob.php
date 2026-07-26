<?php

namespace App\Jobs\ExpoLens;

use App\Models\ExpoLensPhoto;
use App\Services\ExpoLens\FaceService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ProcessExpoLensPhotoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public int $organizationId,
        public int $photoId,
    ) {
        $this->onQueue('media');
    }

    public function handle(FaceService $faceService, TenantContext $tenant): void
    {
        $this->activateTenant($tenant);

        $photo = ExpoLensPhoto::with('file')->findOrFail($this->photoId);
        $photo->update([
            'processing_status' => 'processing',
            'failure_reason' => null,
        ]);

        $result = $faceService->detectAndEmbed($photo->file);
        $faces = $result['faces'] ?? [];
        $minimumQuality = (float) config('services.expolens.minimum_quality', 0.18);
        $threshold = (float) config('services.expolens.match_threshold', 0.45);

        DB::transaction(function () use ($photo, $faces, $result, $minimumQuality, $threshold) {
            $photo->matches()->delete();
            $photo->faces()->delete();

            $accepted = 0;
            foreach ($faces as $face) {
                if (($face['quality_score'] ?? 0) < $minimumQuality) {
                    continue;
                }
                if (count($face['embedding'] ?? []) !== 512) {
                    throw new RuntimeException('Face service returned an invalid photo embedding.');
                }

                $vector = json_encode(array_map('floatval', $face['embedding']), JSON_THROW_ON_ERROR);
                $faceRow = DB::selectOne(
                    'INSERT INTO expolens_photo_faces
                        (organization_id, photo_id, bbox, detection_score, quality_score, model,
                         created_at, updated_at, embedding)
                     VALUES (?, ?, ?::jsonb, ?, ?, ?, ?, ?, ?::vector)
                     RETURNING id',
                    [
                        $this->organizationId,
                        $photo->id,
                        json_encode($face['bbox'] ?? [], JSON_THROW_ON_ERROR),
                        $face['detection_score'] ?? 0,
                        $face['quality_score'] ?? 0,
                        $result['model'] ?? 'buffalo_l',
                        now(),
                        now(),
                        $vector,
                    ],
                );

                $match = DB::selectOne(
                    'SELECT participation_id, 1 - (embedding <=> ?::vector) AS similarity
                       FROM expolens_face_embeddings
                      WHERE event_id = ?
                      ORDER BY embedding <=> ?::vector
                      LIMIT 1',
                    [$vector, $photo->event_id, $vector],
                );

                if ($match && (float) $match->similarity >= $threshold) {
                    DB::statement(
                        'INSERT INTO expolens_photo_matches
                            (event_id, organization_id, photo_id, photo_face_id, participation_id,
                             similarity_score, confirmed, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, ?, false, ?, ?)
                         ON CONFLICT (photo_id, participation_id) DO UPDATE SET
                            photo_face_id = CASE
                                WHEN EXCLUDED.similarity_score > expolens_photo_matches.similarity_score
                                THEN EXCLUDED.photo_face_id ELSE expolens_photo_matches.photo_face_id END,
                            similarity_score = GREATEST(
                                expolens_photo_matches.similarity_score,
                                EXCLUDED.similarity_score
                            ),
                            updated_at = EXCLUDED.updated_at',
                        [
                            $photo->event_id,
                            $this->organizationId,
                            $photo->id,
                            $faceRow->id,
                            $match->participation_id,
                            $match->similarity,
                            now(),
                            now(),
                        ],
                    );
                }

                $accepted++;
            }

            $photo->update([
                'processing_status' => 'ready',
                'faces_count' => $accepted,
                'processed_at' => now(),
            ]);
        });
    }

    public function failed(Throwable $exception): void
    {
        $tenant = app(TenantContext::class);
        $this->activateTenant($tenant);

        ExpoLensPhoto::whereKey($this->photoId)->update([
            'processing_status' => 'failed',
            'failure_reason' => mb_substr($exception->getMessage(), 0, 2000),
        ]);
    }

    private function activateTenant(TenantContext $tenant): void
    {
        $tenant->set($this->organizationId);
        DB::statement("set app.current_organization = '{$this->organizationId}'");
    }
}
