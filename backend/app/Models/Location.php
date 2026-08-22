<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Location extends Model
{
    protected $fillable = [
        'district_id', 'name', 'slug', 'type', 'coord_x', 'coord_y',
        'capacity', 'services', 'is_home_template', 'description',
    ];

    protected function casts(): array
    {
        return [
            'coord_x' => 'float',
            'coord_y' => 'float',
            'services' => 'array',
            'is_home_template' => 'boolean',
        ];
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function toMapArray(): array
    {
        $this->loadMissing('district.city');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'x' => $this->coord_x,
            'y' => $this->coord_y,
            'capacity' => $this->capacity,
            'services' => $this->services ?? [],
            'description' => $this->description,
            'district' => [
                'id' => $this->district?->id,
                'name' => $this->district?->name,
                'slug' => $this->district?->slug,
                'color' => $this->district?->color,
            ],
            'city' => $this->district?->city?->name,
        ];
    }
}
