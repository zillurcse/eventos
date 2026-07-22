<?php

namespace App\Services\Email;

use App\Models\EmailTemplate;
use App\Models\EmailTemplateVersion;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Collection;

/**
 * Snapshot history for email templates (architecture §6.13).
 *
 * The history is append-only. Restoring writes a *new* version carrying the old
 * design forward rather than truncating the tail, so "what did this look like on
 * Tuesday" stays answerable even after someone rolls back.
 */
class TemplateVersions
{
    /**
     * Autosave fires on every pause in typing. Without coalescing, one editing
     * session would fill the history with near-identical rows and push out the
     * checkpoints someone might actually want back. A snapshot taken within
     * this window of the previous one is skipped — that earlier row still
     * represents the state from before this burst of edits.
     */
    public const COALESCE_MINUTES = 2;

    public function __construct(protected TenantContext $tenant) {}

    /**
     * Record the template's current state as the next version.
     *
     * Called *before* the template is updated, so the snapshot captures what is
     * being replaced. No-ops when the design is unchanged, which keeps autosave
     * from burying real edits under hundreds of identical rows.
     *
     * `$force` bypasses the coalescing window for moments that must be
     * recoverable no matter how recently we snapshotted — notably a restore,
     * which would otherwise be able to discard un-snapshotted work.
     */
    public function snapshot(EmailTemplate $template, ?int $userId = null, bool $force = false): ?EmailTemplateVersion
    {
        $latest = $this->latest($template);

        if ($latest && $this->isSameDocument($latest, $template)) {
            return null;
        }

        if (! $force && $latest?->created_at?->gt(now()->subMinutes(self::COALESCE_MINUTES))) {
            return null;
        }

        $version = EmailTemplateVersion::create([
            'template_id' => $template->id,
            // The acting tenant, not the template's — a shared platform
            // template (organization_id NULL) is still edited by someone, and
            // RLS rejects an org-NULL write outright.
            'organization_id' => $this->tenant->id() ?? $template->organization_id,
            'version' => ($latest?->version ?? 0) + 1,
            'name' => $template->name,
            'subject' => $template->subject,
            'preheader' => $template->preheader,
            'design' => $template->design,
            'created_by' => $userId,
            'created_at' => now(),
        ]);

        $this->prune($template);

        return $version;
    }

    /** @return Collection<int,EmailTemplateVersion> newest first */
    public function history(EmailTemplate $template, int $limit = 40): Collection
    {
        return EmailTemplateVersion::where('template_id', $template->id)
            ->orderByDesc('version')
            ->limit($limit)
            ->get();
    }

    /**
     * Copy a past version's document back onto the template. The caller is
     * responsible for snapshotting the current state first (so the restore is
     * itself undoable) and for recompiling afterwards.
     */
    public function restore(EmailTemplate $template, EmailTemplateVersion $version): EmailTemplate
    {
        $template->update([
            'name' => $version->name ?: $template->name,
            'subject' => $version->subject,
            'preheader' => $version->preheader,
            'design' => $version->design,
        ]);

        return $template;
    }

    protected function latest(EmailTemplate $template): ?EmailTemplateVersion
    {
        return EmailTemplateVersion::where('template_id', $template->id)
            ->orderByDesc('version')
            ->first();
    }

    /**
     * Compare what a restore would actually bring back. Timestamps and the
     * version counter are excluded — only the authored document counts.
     */
    protected function isSameDocument(EmailTemplateVersion $version, EmailTemplate $template): bool
    {
        return $version->name === $template->name
            && $version->subject === $template->subject
            && $version->preheader === $template->preheader
            && json_encode($version->design) === json_encode($template->design);
    }

    /** Keep the tail bounded — designs are large and history is unbounded otherwise. */
    protected function prune(EmailTemplate $template): void
    {
        $cutoff = EmailTemplateVersion::where('template_id', $template->id)
            ->orderByDesc('version')
            ->skip(EmailTemplateVersion::KEEP)
            ->take(1)
            ->value('version');

        if ($cutoff !== null) {
            EmailTemplateVersion::where('template_id', $template->id)
                ->where('version', '<=', $cutoff)
                ->delete();
        }
    }
}
