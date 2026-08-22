<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AivvaDailyBudget extends Model
{
    protected $fillable = [
        'aivva_id', 'budget_date', 'actions_used', 'tokens_used', 'spend_used', 'ai_cost_cents',
    ];

    protected function casts(): array
    {
        return [
            'budget_date' => 'date',
        ];
    }

    public function aivva(): BelongsTo
    {
        return $this->belongsTo(Aivva::class);
    }

    public static function todayFor(Aivva $aivva): self
    {
        $date = now()->toDateString();
        $existing = self::query()
            ->where('aivva_id', $aivva->id)
            ->whereDate('budget_date', $date)
            ->first();
        if ($existing) {
            return $existing;
        }

        try {
            return self::query()->create([
                'aivva_id' => $aivva->id,
                'budget_date' => $date,
                'actions_used' => 0,
                'tokens_used' => 0,
                'spend_used' => 0,
                'ai_cost_cents' => 0,
            ]);
        } catch (\Throwable) {
            return self::query()
                ->where('aivva_id', $aivva->id)
                ->whereDate('budget_date', $date)
                ->firstOrFail();
        }
    }
}
