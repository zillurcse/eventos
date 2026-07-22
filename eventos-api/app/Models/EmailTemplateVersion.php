<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An append-only snapshot of a template taken on each save, so a design can be
 * restored and edits are auditable (architecture §6.13). Never updated — a
 * "restore" writes a *new* version carrying the old design forward, which keeps
 * the history honest about what actually happened.
 */
class EmailTemplateVersion extends Model
{
    use BelongsToOrganization;

    public const UPDATED_AT = null;

    /** Snapshots older than this (per template) are pruned on write. */
    public const KEEP = 40;

    protected $guarded = [];

    protected $casts = [
        'design' => 'array',
        'created_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'template_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
