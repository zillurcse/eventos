<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormField extends Model
{
    public $timestamps = false; // form_fields has no created_at/updated_at

    protected $guarded = [];

    protected $casts = [
        'validation' => 'array',
        'default_value' => 'array',
        'is_default' => 'boolean',
        'is_required' => 'boolean',
        'is_unique' => 'boolean',
        'is_pii' => 'boolean',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(FormFieldOption::class, 'field_id');
    }
}
