<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AivvaProfile extends Model
{
    protected $fillable = [
        'aivva_id', 'personality', 'skills', 'interests', 'work_preferences',
        'risk_tolerance', 'bio', 'portrait_seed', 'privacy',
    ];

    protected function casts(): array
    {
        return [
            'skills' => 'array',
            'interests' => 'array',
            'work_preferences' => 'array',
            'privacy' => 'array',
        ];
    }

    public function aivva(): BelongsTo
    {
        return $this->belongsTo(Aivva::class);
    }
}
