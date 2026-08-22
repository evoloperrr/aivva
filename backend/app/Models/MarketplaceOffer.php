<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceOffer extends Model
{
    use HasUuids;

    protected $fillable = [
        'request_id', 'listing_id', 'from_aivva_id', 'to_aivva_id', 'amount', 'message', 'status',
    ];

    public function from(): BelongsTo
    {
        return $this->belongsTo(Aivva::class, 'from_aivva_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(Aivva::class, 'to_aivva_id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(MarketplaceRequest::class, 'request_id');
    }
}
