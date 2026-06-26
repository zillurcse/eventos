<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Per-event sponsor CTA (Communication → CTA). One of three types — image,
 * video or text — surfaced on the attendee website / app.
 */
class Cta extends Model
{
    use BelongsToOrganization, SoftDeletes, HasUuid, Auditable;

    protected $guarded = [];

    protected $casts = [
        'videos' => 'array',
        'position' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function imageFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'image_file_id');
    }
}
