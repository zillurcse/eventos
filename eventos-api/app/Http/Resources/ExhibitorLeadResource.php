<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExhibitorLeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $rep = $this->scannedBy;
        $repName = $rep && $rep->contact
            ? (trim(($rep->contact->first_name ?? '').' '.($rep->contact->last_name ?? '')) ?: $rep->contact->email)
            : null;

        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'job_title' => $this->job_title,
            'rating' => $this->rating,
            'status' => $this->status,
            'source' => $this->source,
            'notes' => $this->notes,
            'scanned_at' => optional($this->scanned_at)->toIso8601String(),
            'exported_at' => optional($this->exported_at)->toIso8601String(),
            'scanned_by' => $repName,
            'scanned_by_member_id' => $this->scanned_by_member_id,
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
