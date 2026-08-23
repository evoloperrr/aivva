<?php

namespace App\Domain\Economy;

use App\Models\Aivva;
use App\Models\MarketplaceRequest;

class MarketplaceScoring
{
    /**
     * Score an open request against an AIVVA's skills. Music demand is
     * penalized unless the AIVVA actually lists a music skill.
     *
     * @param  array<string, mixed>|MarketplaceRequest  $request
     */
    public function score(Aivva $aivva, array|MarketplaceRequest $request): int
    {
        $title = is_array($request) ? (string) ($request['title'] ?? '') : (string) $request->title;
        $category = is_array($request) ? (string) ($request['category'] ?? '') : (string) $request->category;
        $description = is_array($request) ? (string) ($request['description'] ?? '') : (string) ($request->description ?? '');
        $hay = mb_strtolower($title.' '.$category.' '.$description);

        $skills = collect($aivva->profile?->skills ?? [])
            ->map(fn ($skill) => mb_strtolower((string) $skill));
        $hasMusic = $skills->contains(fn ($skill) => str_contains($skill, 'music') || str_contains($skill, 'sound'));
        $hasWriting = $skills->contains(fn ($skill) => str_contains($skill, 'writ') || str_contains($skill, 'creat') || str_contains($skill, 'promo') || str_contains($skill, 'concept'));

        $score = 0;
        foreach (['promo', 'promotional', 'concept', 'writing', 'coffee', 'shop', 'brief', 'content'] as $word) {
            if (str_contains($hay, $word)) {
                $score += 2;
            }
        }
        foreach ($skills as $skill) {
            $token = explode(' ', $skill)[0] ?? '';
            if ($token !== '' && str_contains($hay, $token)) {
                $score += 2;
            }
        }
        if ($hasWriting && (str_contains($hay, 'writing') || str_contains($hay, 'promo') || str_contains($hay, 'concept'))) {
            $score += 4;
        }
        if (str_contains($hay, 'music') && ! $hasMusic) {
            $score -= 8;
        }

        return $score;
    }

    /**
     * @param  iterable<int, array<string, mixed>|MarketplaceRequest>  $requests
     * @return array<string, mixed>|MarketplaceRequest|null
     */
    public function bestMatch(Aivva $aivva, iterable $requests, int $minimum = 2): mixed
    {
        $best = null;
        $bestScore = $minimum - 1;
        foreach ($requests as $request) {
            $score = $this->score($aivva, $request);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $request;
            }
        }

        return $best;
    }
}
