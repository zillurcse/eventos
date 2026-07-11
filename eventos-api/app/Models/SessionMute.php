<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A participant silenced by the host for one session: they can still watch and
 * vote, but cannot post chat or ask questions. Scoped to the session, so a mute
 * in one room doesn't follow someone around the event. Tenant-isolated via RLS.
 */
class SessionMute extends Model
{
    use BelongsToOrganization;

    protected $guarded = [];

    public function participation(): BelongsTo
    {
        return $this->belongsTo(Participation::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }
}
