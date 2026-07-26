<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpoLensPhotoFace extends Model
{
    use BelongsToOrganization;

    protected $table = 'expolens_photo_faces';

    protected $guarded = [];

    protected $hidden = ['embedding'];

    protected $casts = [
        'bbox' => 'array',
        'detection_score' => 'float',
        'quality_score' => 'float',
    ];

    public function photo(): BelongsTo
    {
        return $this->belongsTo(ExpoLensPhoto::class, 'photo_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(ExpoLensPhotoMatch::class, 'photo_face_id');
    }
}
