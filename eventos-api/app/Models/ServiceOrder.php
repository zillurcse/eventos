<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * One basket an exhibitor submitted from "Request Service" (§ Services). The
 * organizer reviews it on Services › Requested Services and approves/rejects
 * each line, so the order's own status is always derived, never stored.
 */
class ServiceOrder extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
        'submitted_at' => 'datetime',
    ];

    /** Order statuses, in the order the UI tabs present them. */
    public const STATUSES = ['pending', 'partial', 'approved', 'rejected'];

    public function exhibitor(): BelongsTo
    {
        return $this->belongsTo(Exhibitor::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class)->orderBy('id');
    }

    /** Next human-facing reference, e.g. SER-20250000003. */
    public static function nextOrderNumber(): string
    {
        $seq = DB::selectOne("SELECT nextval('service_order_number_seq') AS n")->n;

        return 'SER-'.date('Y').str_pad((string) $seq, 7, '0', STR_PAD_LEFT);
    }

    /**
     * Roll the line statuses up into one order status: unanimous lines carry
     * their own status through, anything mixed is "partial".
     */
    public static function rollUpStatus(int $pending, int $approved, int $rejected): string
    {
        return match (true) {
            $pending === 0 && $approved === 0 && $rejected === 0 => 'pending',
            $pending > 0 && $approved === 0 && $rejected === 0 => 'pending',
            $approved > 0 && $pending === 0 && $rejected === 0 => 'approved',
            $rejected > 0 && $pending === 0 && $approved === 0 => 'rejected',
            default => 'partial',
        };
    }

    public function status(): string
    {
        $lines = $this->relationLoaded('requests') ? $this->requests : $this->requests()->get();

        return static::rollUpStatus(
            $lines->where('status', 'pending')->count(),
            $lines->where('status', 'approved')->count(),
            $lines->where('status', 'rejected')->count(),
        );
    }
}
