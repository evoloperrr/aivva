<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'aivva_id', 'from_location_id', 'to_location_id', 'distance',
        'world_minutes_duration', 'started_at', 'arrives_at', 'completed_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'distance' => 'float',
            'started_at' => 'datetime',
            'arrives_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function aivva(): BelongsTo
    {
        return $this->belongsTo(Aivva::class);
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function hasArrived(): bool
    {
        return $this->status === 'TRAVELING' && $this->arrives_at->lte(now());
    }
}
