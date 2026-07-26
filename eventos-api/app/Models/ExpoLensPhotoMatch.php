<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpoLensPhotoMatch extends Model
{
    use BelongsToOrganization;

    protected $table = 'expolens_photo_matches';

    protected $guarded = [];

    protected $casts = [
        'similarity_score' => 'float',
        'confirmed' => 'boolean',
    ];

    public function photo(): BelongsTo
    {
        return $this->belongsTo(ExpoLensPhoto::class, 'photo_id');
    }

    public function face(): BelongsTo
    {
        return $this->belongsTo(ExpoLensPhotoFace::class, 'photo_face_id');
    }

    public function participation(): BelongsTo
    {
        return $this->belongsTo(Participation::class);
    }
}
