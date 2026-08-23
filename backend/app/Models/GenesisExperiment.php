<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class GenesisExperiment extends Model
{
    use HasUuids;

    protected $fillable = [
        'code', 'status', 'outcome', 'brain_mode', 'provider', 'model',
        'luna_id', 'nova_id', 'conversation_id', 'request_id', 'order_id',
        'work_id', 'verification_id', 'agreed_price', 'human_interventions',
        'transcript', 'usage', 'public_summaries', 'ledger_ids',
    ];

    protected function casts(): array
    {
        return [
            'transcript' => 'array',
            'usage' => 'array',
            'public_summaries' => 'array',
            'ledger_ids' => 'array',
        ];
    }
}
