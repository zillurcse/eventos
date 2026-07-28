<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExpoLensPhotoResource;
use App\Jobs\ExpoLens\EnrollParticipationFaceJob;
use App\Models\ExpoLensFaceEmbedding;
use App\Models\ExpoLensPhoto;
use App\Models\File;
use App\Models\Participation;
use App\Services\ExpoLens\ExpoLensRetention;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ParticipantExpoLensController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return ExpoLensPhotoResource::collection(
            $this->approvedPhotos($request)
                ->paginate(min($request->integer('per_page', 30), 60))
        );
    }

    public function myPhotos(Request $request): AnonymousResourceCollection
    {
        $participationId = (int) $request->attributes->get('participation_id');

        return ExpoLensPhotoResource::collection(
            $this->approvedPhotos($request)
                ->whereHas('matches', fn ($q) => $q->where('participation_id', $participationId))
                ->paginate(min($request->integer('per_page', 30), 60))
        )->additional(['meta' => $this->enrollmentMeta($participationId)]);
    }

    public function enrollment(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->enrollmentMeta((int) $request->attributes->get('participation_id')),
        ]);
    }

    public function enroll(Request $request): JsonResponse
    {
        $data = $request->validate([
            'consent' => ['required', 'accepted'],
            'file_id' => ['nullable', 'integer'],
        ]);

        $participation = Participation::findOrFail((int) $request->attributes->get('participation_id'));
        $profile = $participation->profile_data ?? [];
        $fileId = $data['file_id'] ?? ($profile['avatar_file_id'] ?? null);

        abort_unless($fileId, 422, 'Upload a clear profile photo before enabling face matching.');

        $file = File::where('organization_id', $participation->organization_id)
            ->whereIn('collection', ['avatar', 'expolens'])
            ->find($fileId);
        abort_unless($file, 422, 'The selected enrollment photo is invalid.');

        $profile['expolens_consent_at'] = now()->toIso8601String();
        $profile['avatar_file_id'] = $file->id;
        $profile['expolens_enrollment_status'] = 'processing';
        unset($profile['expolens_enrollment_error']);
        $participation->update(['profile_data' => $profile]);

        EnrollParticipationFaceJob::dispatch(
            $participation->organization_id,
            $participation->event_id,
            $participation->id,
            $file->id,
        );

        return response()->json([
            'message' => 'Your face enrollment is processing.',
            'data' => [
                'consented' => true,
                'enrolled' => false,
                'status' => 'processing',
            ],
        ], 202);
    }

    public function withdraw(Request $request): JsonResponse
    {
        $participation = Participation::findOrFail((int) $request->attributes->get('participation_id'));

        app(ExpoLensRetention::class)->purgeParticipation($participation->id);

        $profile = $participation->profile_data ?? [];
        unset(
            $profile['expolens_consent_at'],
            $profile['expolens_enrollment_status'],
            $profile['expolens_enrollment_error'],
        );
        $participation->update(['profile_data' => $profile]);

        return response()->json(['message' => 'Face matching consent and biometric data were removed.']);
    }

    private function approvedPhotos(Request $request)
    {
        return ExpoLensPhoto::where('event_id', (int) $request->attributes->get('event_id'))
            ->where('processing_status', 'ready')
            ->where('moderation_status', 'approved')
            ->latest();
    }

    private function enrollmentMeta(int $participationId): array
    {
        $participation = Participation::findOrFail($participationId);
        $embedding = ExpoLensFaceEmbedding::where('participation_id', $participationId)->first();
        $profile = $participation->profile_data ?? [];
        $consented = (bool) data_get($profile, 'expolens_consent_at');

        // A stored embedding is the ground truth; otherwise fall back to what
        // the enrollment job last recorded, so a failed enrollment stops
        // reporting "processing" forever.
        $status = match (true) {
            (bool) $embedding => 'ready',
            ! $consented => 'not_enrolled',
            default => (string) (data_get($profile, 'expolens_enrollment_status') ?: 'processing'),
        };

        return [
            'consented' => $consented,
            'enrolled' => (bool) $embedding,
            'status' => $status,
            'error' => $status === 'failed'
                ? (string) data_get($profile, 'expolens_enrollment_error')
                : null,
            'enrolled_at' => $embedding?->enrolled_at?->toIso8601String(),
            'quality_score' => $embedding?->quality_score,
        ];
    }
}
