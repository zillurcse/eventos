<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Event;
use App\Models\EventSetting;

/**
 * The "where do we meet?" side of a one-to-one meeting request.
 *
 * A venue or hybrid event happens on a floor, so every meeting needs a physical
 * place ("Hall 4"); an online event has none and the field stays NULL. The
 * organizer may pre-define the places attendees can choose from (Admin →
 * Communication → Meetings), but those are suggestions: the requester can always
 * type a place of their own instead. Either way the meeting stores the final
 * label, so re-editing the list later never rewrites existing meetings.
 */
trait HandlesMeetingLocation
{
    /** Formats that put people in a room together. */
    protected function isPhysicalEvent(Event $event): bool
    {
        return in_array($event->format, ['venue', 'hybrid'], true);
    }

    /** The organizer's pre-defined meeting places, e.g. ["Hall 4", "Lounge B"]. */
    protected function meetingLocationOptions(int $eventId): array
    {
        $meeting = EventSetting::where('event_id', $eventId)->value('meeting');

        $locations = is_array($meeting['locations'] ?? null) ? $meeting['locations'] : [];

        return collect($locations)
            ->filter(fn ($l) => is_string($l) && trim($l) !== '')
            ->map(fn (string $l) => trim($l))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Validation rules for the `location` field of a meeting request: required
     * on a venue/hybrid event, free text otherwise unconstrained. The
     * organizer's list is a set of suggestions, not a whitelist — the two people
     * meeting may well agree on a spot the organizer never listed ("Booth B12",
     * "the coffee bar"), so we take whatever they type.
     *
     * @return array<int,mixed>
     */
    protected function meetingLocationRules(Event $event): array
    {
        return $this->isPhysicalEvent($event)
            ? ['required', 'string', 'max:180']
            : ['nullable', 'string', 'max:180'];
    }

    /** The value to persist: NULL on an online event, the trimmed label otherwise. */
    protected function meetingLocationValue(Event $event, ?string $input): ?string
    {
        if (! $this->isPhysicalEvent($event)) {
            return null;
        }

        return trim((string) $input) ?: null;
    }
}
