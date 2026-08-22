<?php

namespace App\Domain\Trust;

use App\Models\Aivva;
use App\Models\LifePointEvent;
use App\Models\TrustScore;

class TrustService
{
    public function ensure(Aivva $aivva): TrustScore
    {
        return TrustScore::query()->firstOrCreate(
            ['aivva_id' => $aivva->id],
            ['economic' => 50, 'social' => 50, 'skills' => [], 'overall' => 50],
        );
    }

    public function bump(Aivva $aivva, string $dimension, int $delta, ?string $skill = null): TrustScore
    {
        $score = $this->ensure($aivva);
        if ($dimension === 'economic') {
            $score->economic = $this->clamp($score->economic + $delta);
        }
        if ($dimension === 'social') {
            $score->social = $this->clamp($score->social + $delta);
        }
        if ($skill) {
            $skills = $score->skills ?? [];
            $skills[$skill] = $this->clamp(($skills[$skill] ?? 50) + $delta);
            $score->skills = $skills;
        }
        $score->recomputeOverall();

        return $score->fresh();
    }

    public function awardLifePoints(Aivva $aivva, int $delta, string $reason): void
    {
        if ($delta === 0) {
            return;
        }
        LifePointEvent::query()->create([
            'aivva_id' => $aivva->id,
            'delta' => $delta,
            'reason' => $reason,
        ]);
        $aivva->increment('life_points', $delta);
    }

    private function clamp(int $value): int
    {
        return max(0, min(100, $value));
    }
}
