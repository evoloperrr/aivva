<?php

namespace App\Models;

use App\Enums\MessageIntent;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AivvaMessage extends Model
{
    use HasUuids;

    protected $fillable = [
        'conversation_id', 'from_aivva_id', 'to_aivva_id', 'intent',
        'message_type', 'action', 'payload', 'natural_language', 'layer',
        'read', 'status', 'turn_number', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'intent' => MessageIntent::class,
            'payload' => 'array',
            'read' => 'boolean',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AivvaConversation::class, 'conversation_id');
    }

    public function from(): BelongsTo
    {
        return $this->belongsTo(Aivva::class, 'from_aivva_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(Aivva::class, 'to_aivva_id');
    }
}
