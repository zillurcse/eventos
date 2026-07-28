<?php

namespace App\Services\ExpoLens;

use Illuminate\Support\Facades\DB;

/**
 * Turns stored face vectors into attendee matches.
 *
 * Matching is pure pgvector work — it never calls the face service — so it can
 * run cheaply in two directions:
 *   - a new photo arrives          → match its faces against enrolled attendees
 *   - an attendee enrols late      → match them against already-detected faces
 *
 * Both directions write to expolens_photo_matches, which is unique on
 * (photo_id, participation_id): one attendee appears at most once per photo.
 */
class FaceMatcher
{
    /**
     * Match every detected face of a photo against the event's enrolled faces.
     *
     * For each face we pull the N nearest enrolments rather than only the
     * single closest, then assign greedily by descending similarity. Without
     * that, two faces in a group shot whose nearest neighbour is the same
     * person would collapse into one match and silently drop a real attendee.
     *
     * @return int number of attendees matched in this photo
     */
    public function matchPhoto(int $organizationId, int $eventId, int $photoId): int
    {
        $threshold = $this->threshold();

        $rows = DB::select(
            'SELECT f.id AS face_id, c.participation_id, 1 - (f.embedding <=> c.embedding) AS similarity
               FROM expolens_photo_faces f
               CROSS JOIN LATERAL (
                    SELECT participation_id, embedding
                      FROM expolens_face_embeddings
                     WHERE event_id = ?
                     ORDER BY embedding <=> f.embedding
                     LIMIT ?
               ) c
              WHERE f.photo_id = ?
              ORDER BY similarity DESC',
            [$eventId, $this->candidates(), $photoId],
        );

        // Matches that survived into this pass (a rematch keeps organizer-
        // confirmed ones) already own their face and their attendee. Seed them
        // in, or the greedy pass below could hand the same face to a second
        // person — the unique key is (photo_id, participation_id), so nothing
        // at the DB level would catch that.
        $usedFaces = [];
        $usedParticipations = [];

        foreach (DB::select(
            'SELECT photo_face_id, participation_id FROM expolens_photo_matches WHERE photo_id = ?',
            [$photoId],
        ) as $existing) {
            $usedFaces[$existing->photo_face_id] = true;
            $usedParticipations[$existing->participation_id] = true;
        }

        $matched = 0;

        foreach ($rows as $row) {
            if ((float) $row->similarity < $threshold) {
                break; // rows are sorted desc — nothing after this qualifies
            }
            if (isset($usedFaces[$row->face_id]) || isset($usedParticipations[$row->participation_id])) {
                continue;
            }

            $this->writeMatch(
                $organizationId,
                $eventId,
                $photoId,
                (int) $row->face_id,
                (int) $row->participation_id,
                (float) $row->similarity,
            );

            $usedFaces[$row->face_id] = true;
            $usedParticipations[$row->participation_id] = true;
            $matched++;
        }

        return $matched;
    }

    /**
     * Match one attendee against every face already detected for the event.
     *
     * This is what makes late enrolment work: photos processed before the
     * attendee consented still surface in their gallery, with no face-service
     * round trip and no full reprocess.
     *
     * @return int number of photos the attendee was matched into
     */
    public function matchParticipation(int $organizationId, int $eventId, int $participationId): int
    {
        $embedding = DB::selectOne(
            'SELECT embedding::text AS embedding
               FROM expolens_face_embeddings
              WHERE participation_id = ?
              ORDER BY enrolled_at DESC
              LIMIT 1',
            [$participationId],
        );

        if (! $embedding) {
            return 0;
        }

        $rows = DB::select(
            'SELECT f.id AS face_id, f.photo_id, 1 - (f.embedding <=> ?::vector) AS similarity
               FROM expolens_photo_faces f
               JOIN expolens_photos p ON p.id = f.photo_id
              WHERE p.event_id = ?
                AND p.deleted_at IS NULL
                AND 1 - (f.embedding <=> ?::vector) >= ?
              ORDER BY similarity DESC',
            [$embedding->embedding, $eventId, $embedding->embedding, $this->threshold()],
        );

        $matched = 0;
        $seenPhotos = [];

        foreach ($rows as $row) {
            if (isset($seenPhotos[$row->photo_id])) {
                continue; // already took this attendee's best face in that photo
            }

            // A face belongs to one person. If someone else already holds this
            // one, take it only on a strictly better score — first-come would
            // otherwise let a weaker match lock out the right attendee. A full
            // rematch re-solves the whole photo jointly; this incremental path
            // just avoids leaving the better claim unmatched.
            $incumbent = DB::selectOne(
                'SELECT participation_id, similarity_score, confirmed
                   FROM expolens_photo_matches
                  WHERE photo_face_id = ? AND participation_id <> ?
                  LIMIT 1',
                [$row->face_id, $participationId],
            );

            if ($incumbent) {
                // Never override a match an organizer confirmed by hand.
                if ($incumbent->confirmed || (float) $incumbent->similarity_score >= (float) $row->similarity) {
                    continue;
                }

                DB::delete(
                    'DELETE FROM expolens_photo_matches WHERE photo_face_id = ? AND participation_id = ?',
                    [$row->face_id, $incumbent->participation_id],
                );
            }

            $this->writeMatch(
                $organizationId,
                $eventId,
                (int) $row->photo_id,
                (int) $row->face_id,
                $participationId,
                (float) $row->similarity,
            );

            $seenPhotos[$row->photo_id] = true;
            $matched++;
        }

        return $matched;
    }

    private function writeMatch(
        int $organizationId,
        int $eventId,
        int $photoId,
        int $faceId,
        int $participationId,
        float $similarity,
    ): void {
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
                $eventId,
                $organizationId,
                $photoId,
                $faceId,
                $participationId,
                $similarity,
                now(),
                now(),
            ],
        );
    }

    private function threshold(): float
    {
        return (float) config('services.expolens.match_threshold', 0.45);
    }

    private function candidates(): int
    {
        return max(1, (int) config('services.expolens.match_candidates', 5));
    }
}
