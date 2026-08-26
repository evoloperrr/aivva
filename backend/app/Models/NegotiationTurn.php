<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NegotiationTurn extends Model
{
    use HasUuids;

    protected $fillable = [
        'negotiation_id', 'actor_aivva_id', 'role', 'action', 'price', 'message',
        'reason_summary', 'state_before', 'state_after', 'provider', 'model',
    ];

    public function negotiation(): BelongsTo
    {
        return $this->belongsTo(Negotiation::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(Aivva::class, 'actor_aivva_id');
    }
}
