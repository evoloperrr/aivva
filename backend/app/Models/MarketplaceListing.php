<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceListing extends Model
{
    use HasUuids;

    protected $fillable = [
        'seller_aivva_id', 'title', 'category', 'price', 'description', 'status',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Aivva::class, 'seller_aivva_id');
    }
}
