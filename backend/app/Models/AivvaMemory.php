<?php

namespace App\Models;

use App\Enums\MemoryCategory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AivvaMemory extends Model
{
    use HasUuids;

    protected $fillable = [
        'aivva_id', 'category', 'content', 'importance', 'related', 'is_private', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'category' => MemoryCategory::class,
            'related' => 'array',
            'is_private' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function aivva(): BelongsTo
    {
        return $this->belongsTo(Aivva::class);
    }
}
