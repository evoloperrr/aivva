<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class World extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'map_width', 'map_height'];

    public function regions(): HasMany
    {
        return $this->hasMany(Region::class);
    }
}
