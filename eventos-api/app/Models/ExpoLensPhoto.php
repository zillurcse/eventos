<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpoLensPhoto extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    protected $table = 'expolens_photos';

    protected $guarded = [];

    protected $casts = [
        'photographer_meta' => 'array',
        'processed_at' => 'datetime',
        'faces_count' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function faces(): HasMany
    {
        return $this->hasMany(ExpoLensPhotoFace::class, 'photo_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(ExpoLensPhotoMatch::class, 'photo_id');
    }
}
