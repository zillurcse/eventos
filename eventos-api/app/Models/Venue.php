<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venue extends Model
{
    use BelongsToOrganization;

    protected $guarded = [];

    protected $casts = ['latitude' => 'float', 'longitude' => 'float'];

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }
}
