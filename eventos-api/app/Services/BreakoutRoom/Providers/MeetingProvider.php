<?php

namespace App\Services\BreakoutRoom\Providers;

use App\Models\BreakoutRoom;

/**
 * Media backend for a breakout room (Strategy pattern). Resolved by the room's
 * `provider` column. `webrtc` → LiveKitProvider (self-hosted SFU); Zoom/Teams/
 * Meet/Jitsi/BBB/External are additional implementations on the roadmap.
 */
interface MeetingProvider
{
    /**
     * Connection details the client needs to join.
     *
     * @param  array{identity:string,name:string,role:string}  $participant
     * @return array{provider:string,url:string,token:string,room:string}
     */
    public function joinConfig(BreakoutRoom $room, array $participant): array;

    /** Force a participant out of a live room. */
    public function removeParticipant(BreakoutRoom $room, string $identity): void;

    /** Lock (or unlock) a room so no new participants may join. */
    public function setLocked(BreakoutRoom $room, bool $locked): void;

    /** Begin server-side recording; returns a provider recording/egress id. */
    public function startRecording(BreakoutRoom $room): string;

    /** Stop an in-progress recording. */
    public function stopRecording(BreakoutRoom $room, string $recordingId): void;
}
