<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    use HasUuids;

    protected $fillable = [
        'owner_type', 'owner_id', 'currency', 'available_balance', 'held_balance',
    ];

    public function accounts(): HasMany
    {
        return $this->hasMany(LedgerAccount::class);
    }

    public function availableAccount(): LedgerAccount
    {
        return $this->accounts()->where('code', 'wallet:'.$this->id.':available')->firstOrFail();
    }

    public function heldAccount(): LedgerAccount
    {
        return $this->accounts()->where('code', 'wallet:'.$this->id.':held')->firstOrFail();
    }
}
