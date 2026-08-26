<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Negotiation extends Model
{
    use HasUuids;

    protected $fillable = [
        'request_id', 'buyer_aivva_id', 'seller_aivva_id', 'state', 'next_actor',
        'active_offer_amount', 'active_offer_by', 'turn_count', 'max_turns',
        'spent_cost_cents', 'outcome', 'agreed_price', 'agreement', 'order_id', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'agreement' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public const TERMINAL_STATES = ['AGREED', 'DECLINED', 'CANCELLED', 'EXPIRED', 'ESCROW_FUNDED'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(MarketplaceRequest::class, 'request_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Aivva::class, 'buyer_aivva_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Aivva::class, 'seller_aivva_id');
    }

    public function turns(): HasMany
    {
        return $this->hasMany(NegotiationTurn::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->state, self::TERMINAL_STATES, true);
    }

    public function roleFor(Aivva $aivva): ?string
    {
        if ($aivva->id === $this->buyer_aivva_id) {
            return 'buyer';
        }
        if ($aivva->id === $this->seller_aivva_id) {
            return 'seller';
        }

        return null;
    }
}
