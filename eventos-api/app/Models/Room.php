<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Room extends Model
{
    use BelongsToOrganization;

    protected $guarded = [];

    protected $casts = ['meta' => 'array'];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}
