<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceItem extends Model
{
    use BelongsToOrganization, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'rate' => 'float',
        'tax' => 'float',
        'discount' => 'float',
        'quantity_available' => 'integer',
        'is_active' => 'boolean',
        'enable_discount' => 'boolean',
        'dynamic_pricing' => 'boolean',
        'rate_conditions' => 'array',
        'discount_start_date' => 'date',
        'discount_end_date' => 'date',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }
}
