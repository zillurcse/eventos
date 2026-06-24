<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Track extends Model
{
    use BelongsToOrganization;

    protected $guarded = [];

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class);
    }
}
