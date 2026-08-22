<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LedgerTransaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'type', 'reference', 'description', 'meta', 'settled_at', 'reversed', 'reverses_id',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'settled_at' => 'datetime',
            'reversed' => 'boolean',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }
}
