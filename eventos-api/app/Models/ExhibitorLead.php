<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A prospect captured by an exhibitor booth (§6.3). Scoped to its org via RLS
 * and to its booth via exhibitor_id.
 */
class ExhibitorLead extends Model
{
    use BelongsToOrganization, SoftDeletes, HasUuid;

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
        'scanned_at' => 'datetime',
        'exported_at' => 'datetime',
    ];

    public const RATINGS = ['hot', 'warm', 'cold'];
    public const STATUSES = ['pending', 'connected', 'contacted', 'qualified', 'won', 'lost'];

    public function exhibitor(): BelongsTo
    {
        return $this->belongsTo(Exhibitor::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function scannedBy(): BelongsTo
    {
        return $this->belongsTo(ExhibitorMember::class, 'scanned_by_member_id');
    }

    public function participation(): BelongsTo
    {
        return $this->belongsTo(Participation::class);
    }
}
