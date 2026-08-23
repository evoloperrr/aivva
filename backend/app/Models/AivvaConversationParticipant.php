<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AivvaConversationParticipant extends Model
{
    use HasUuids;

    protected $fillable = [
        'conversation_id', 'aivva_id', 'joined_at', 'left_at',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AivvaConversation::class, 'conversation_id');
    }

    public function aivva(): BelongsTo
    {
        return $this->belongsTo(Aivva::class);
    }
}
