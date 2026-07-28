<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpoLensUploadLink extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $table = 'expolens_upload_links';

    protected $guarded = [];

    protected $hidden = ['token'];

    protected $casts = [
        'auto_approve' => 'boolean',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_used_at' => 'datetime',
        'max_uploads' => 'integer',
        'uploads_count' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isExhausted(): bool
    {
        return $this->max_uploads !== null && $this->uploads_count >= $this->max_uploads;
    }

    public function isUsable(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired() && ! $this->isExhausted();
    }

    /** Uploads still allowed, or null when the link is uncapped. */
    public function remaining(): ?int
    {
        return $this->max_uploads === null
            ? null
            : max(0, $this->max_uploads - $this->uploads_count);
    }
}
