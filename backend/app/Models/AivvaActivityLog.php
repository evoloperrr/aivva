<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AivvaActivityLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'aivva_id', 'action_id', 'kind', 'headline', 'body',
        'world_minutes', 'meta', 'notify_owner',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'notify_owner' => 'boolean',
        ];
    }

    public function aivva(): BelongsTo
    {
        return $this->belongsTo(Aivva::class);
    }

    public function clock(): string
    {
        $hours = intdiv($this->world_minutes, 60) % 24;
        $minutes = $this->world_minutes % 60;

        return sprintf('%02d:%02d', $hours, $minutes);
    }
}
