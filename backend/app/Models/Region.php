<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    protected $fillable = ['world_id', 'name', 'slug'];

    public function world(): BelongsTo
    {
        return $this->belongsTo(World::class);
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }
}
