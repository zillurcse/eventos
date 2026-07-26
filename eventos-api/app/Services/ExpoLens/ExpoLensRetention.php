<?php

namespace App\Services\ExpoLens;

use App\Models\ExpoLensFaceEmbedding;
use App\Models\ExpoLensPhoto;
use App\Models\ExpoLensPhotoFace;
use App\Models\ExpoLensPhotoMatch;
use App\Models\Participation;
use Illuminate\Support\Facades\DB;

/**
 * Biometric retention helpers. Face embeddings are event-scoped and must be
 * removable when an attendee withdraws consent, a contact is erased, or an
 * event is purged.
 */
class ExpoLensRetention
{
    public function purgeEvent(int $organizationId, int $eventId): void
    {
        DB::transaction(function () use ($organizationId, $eventId) {
            ExpoLensPhotoMatch::where('event_id', $eventId)
                ->where('organization_id', $organizationId)
                ->delete();

            $photoIds = ExpoLensPhoto::withTrashed()
                ->where('event_id', $eventId)
                ->where('organization_id', $organizationId)
                ->pluck('id');

            if ($photoIds->isNotEmpty()) {
                ExpoLensPhotoFace::whereIn('photo_id', $photoIds)->delete();
            }

            ExpoLensFaceEmbedding::where('event_id', $eventId)
                ->where('organization_id', $organizationId)
                ->delete();

            ExpoLensPhoto::withTrashed()
                ->where('event_id', $eventId)
                ->where('organization_id', $organizationId)
                ->forceDelete();

            Participation::where('event_id', $eventId)
                ->where('organization_id', $organizationId)
                ->orderBy('id')
                ->chunkById(200, function ($rows) {
                    foreach ($rows as $participation) {
                        $profile = $participation->profile_data ?? [];
                        if (! array_key_exists('expolens_consent_at', $profile)) {
                            continue;
                        }
                        unset($profile['expolens_consent_at']);
                        $participation->update(['profile_data' => $profile]);
                    }
                });
        });
    }

    public function purgeParticipation(int $participationId): void
    {
        DB::transaction(function () use ($participationId) {
            ExpoLensPhotoMatch::where('participation_id', $participationId)->delete();
            ExpoLensFaceEmbedding::where('participation_id', $participationId)->delete();
        });
    }
}
