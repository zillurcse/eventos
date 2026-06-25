<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Per-event blog / news article (Content Hub → Blog). Surfaced on the attendee
 * website once status = published.
 */
class BlogPost extends Model
{
    use BelongsToOrganization, SoftDeletes, HasUuid, Auditable;

    protected $guarded = [];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function coverFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'cover_file_id');
    }

    public function scopePublished($q)
    {
        return $q->where('status', 'published');
    }
}
