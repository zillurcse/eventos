<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An exhibitor's request for one catalogue service_item (§ Services). Scoped to
 * its org via RLS and to its booth via exhibitor_id.
 */
class ServiceRequest extends Model
{
    use BelongsToOrganization, SoftDeletes, HasUuid;

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
        'unit_price' => 'float',
        'quantity' => 'integer',
    ];

    public const STATUSES = ['pending', 'approved', 'rejected'];

    public function exhibitor(): BelongsTo
    {
        return $this->belongsTo(Exhibitor::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function serviceItem(): BelongsTo
    {
        return $this->belongsTo(ServiceItem::class);
    }
}
