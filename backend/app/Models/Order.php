<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasUuids;

    protected $fillable = [
        'buyer_aivva_id', 'seller_aivva_id', 'request_id', 'listing_id',
        'offer_id', 'work_id', 'amount', 'status', 'idempotency_key',
    ];

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Aivva::class, 'buyer_aivva_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Aivva::class, 'seller_aivva_id');
    }

    public function escrow(): HasOne
    {
        return $this->hasOne(Escrow::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(MarketplaceRequest::class, 'request_id');
    }

    public function work(): BelongsTo
    {
        return $this->belongsTo(CreatedWork::class, 'work_id');
    }
}
