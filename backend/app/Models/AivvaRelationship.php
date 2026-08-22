<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AivvaRelationship extends Model
{
    use HasUuids;

    protected $fillable = [
        'aivva_id', 'other_aivva_id', 'type', 'strength', 'trust',
        'interaction_count', 'last_interaction_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'last_interaction_at' => 'datetime',
        ];
    }

    public function aivva(): BelongsTo
    {
        return $this->belongsTo(Aivva::class);
    }

    public function other(): BelongsTo
    {
        return $this->belongsTo(Aivva::class, 'other_aivva_id');
    }
}
