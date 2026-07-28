<?php

namespace App\Jobs\ExpoLens;

use App\Models\File;
use App\Models\Participation;
use App\Services\ExpoLens\FaceService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

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
        $this->activateTenant($tenant);

        $participation = Participation::where('event_id', $this->eventId)->findOrFail($this->participationId);
        $file = File::where('organization_id', $this->organizationId)->findOrFail($this->fileId);

        $consentedAt = data_get($participation->profile_data, 'expolens_consent_at');
        if (! $consentedAt) {
            throw new RuntimeException('ExpoLens biometric consent is required before enrollment.');
        }

        try {
            $result = $faceService->embed($file);
        } catch (RequestException $exception) {
            // A photo with no face — or several — will never succeed on retry,
            // so fail immediately with wording the attendee can act on.
            if ($exception->response->status() === 422) {
                $this->fail(new RuntimeException($this->friendlyError($exception)));

                return;
            }

            throw $exception;
        }

        $face = $result['face'] ?? null;

        if (! is_array($face) || count($face['embedding'] ?? []) !== 512) {
            throw new RuntimeException('Face service returned an invalid enrollment embedding.');
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

        $this->writeStatus($participation->fresh(), 'ready', null);

        // Photos uploaded before this attendee consented still need matching.
        MatchParticipationFacesJob::dispatch(
            $this->organizationId,
            $this->eventId,
            $this->participationId,
        );
    }

    public function failed(Throwable $exception): void
    {
        $this->activateTenant(app(TenantContext::class));

        $participation = Participation::find($this->participationId);
        if ($participation) {
            $this->writeStatus($participation, 'failed', $exception->getMessage());
        }
    }

    private function writeStatus(Participation $participation, string $status, ?string $error): void
    {
        $profile = $participation->profile_data ?? [];
        $profile['expolens_enrollment_status'] = $status;

        if ($error === null) {
            unset($profile['expolens_enrollment_error']);
        } else {
            $profile['expolens_enrollment_error'] = mb_substr($error, 0, 300);
        }

        $participation->update(['profile_data' => $profile]);
    }

    private function friendlyError(RequestException $exception): string
    {
        $detail = (string) data_get($exception->response->json(), 'detail', '');

        if (str_contains($detail, 'detected 0')) {
            return 'We could not find a face in that photo. Upload a clear, front-facing photo of just you.';
        }

        if (str_contains($detail, 'exactly one face')) {
            return 'That photo has more than one face in it. Upload a photo showing only you.';
        }

        return $detail !== '' ? $detail : 'That photo could not be used for face matching.';
    }

    private function activateTenant(TenantContext $tenant): void
    {
        $tenant->set($this->organizationId);
        DB::statement("set app.current_organization = '{$this->organizationId}'");
    }
}
