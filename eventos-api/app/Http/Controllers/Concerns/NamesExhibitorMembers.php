<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ExhibitorMember;
use Illuminate\Support\Collection;

/**
 * Booth teammates rendered the way every exhibitor screen shows them: the
 * contact's full name, falling back to their email, falling back to the member
 * id so a half-provisioned member never renders as an empty cell.
 */
trait NamesExhibitorMembers
{
    protected function memberName(?ExhibitorMember $member): ?string
    {
        if (! $member) {
            return null;
        }

        $contact = $member->contact;

        return $contact
            ? (trim(($contact->first_name ?? '').' '.($contact->last_name ?? '')) ?: (string) $contact->email)
            : 'Teammate #'.$member->id;
    }

    /** @return Collection<int, string> member id => display name */
    protected function memberNames(int $exhibitorId): Collection
    {
        return ExhibitorMember::with('contact')
            ->where('exhibitor_id', $exhibitorId)
            ->get()
            ->mapWithKeys(fn (ExhibitorMember $m) => [$m->id => $this->memberName($m)]);
    }

    /** The team as the client needs it for owner pickers. */
    protected function teamOptions(int $exhibitorId): array
    {
        return ExhibitorMember::with('contact')
            ->where('exhibitor_id', $exhibitorId)
            ->orderBy('id')
            ->get()
            ->map(fn (ExhibitorMember $m) => [
                'id' => $m->id,
                'name' => $this->memberName($m),
                'role' => $m->role,
            ])->values()->all();
    }
}
