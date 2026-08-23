<?php

namespace App\Models;

use App\Enums\EscrowStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Escrow extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_id', 'amount', 'status', 'locked_at', 'settled_at', 'settle_idempotency_key', 'refund_idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'status' => EscrowStatus::class,
            'locked_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
