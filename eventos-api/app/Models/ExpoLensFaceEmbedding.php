<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpoLensFaceEmbedding extends Model
{
    use BelongsToOrganization;

    protected $table = 'expolens_face_embeddings';

    protected $guarded = [];

    protected $hidden = ['embedding'];

    protected $casts = [
        'consented_at' => 'datetime',
        'enrolled_at' => 'datetime',
        'quality_score' => 'float',
        'dimensions' => 'integer',
    ];

    public function participation(): BelongsTo
    {
        return $this->belongsTo(Participation::class);
    }
}
