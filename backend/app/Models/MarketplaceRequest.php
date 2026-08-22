<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'buyer_aivva_id', 'title', 'category', 'budget_min', 'budget_max', 'description', 'status',
    ];

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Aivva::class, 'buyer_aivva_id');
    }
}
