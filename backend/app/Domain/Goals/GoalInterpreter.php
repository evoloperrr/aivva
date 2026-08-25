<?php

namespace App\Domain\Goals;

use App\Ai\AiOrchestrator;
use App\Domain\Ethics\EthicsEngine;
use App\Domain\World\LocationResolver;
use App\Enums\GoalStatus;
use App\Models\Aivva;
use App\Models\AivvaGoal;

class GoalInterpreter
{
    public function __construct(
        private readonly EthicsEngine $ethics,
        private readonly AiOrchestrator $ai,
        private readonly LocationResolver $locations,
    ) {}

    /**
     * @return array{goal: AivvaGoal, interpretation: array<string, mixed>}
     */
    public function interpret(Aivva $aivva, string $direction): array
    {
        $ethics = $this->ethics->reviewDirection($direction);
        if (! $ethics['allowed']) {
            $goal = AivvaGoal::query()->create([
                'aivva_id' => $aivva->id,
                'raw_direction' => $direction,
                'goal_type' => 'Rejected',
                'structured' => [
                    'goal_type' => 'Rejected',
                    'ethical_constraint' => ['Platform rules', 'Safety policies'],
                    'risk_level' => 'Blocked',
                    'priority' => 'None',
                    'time_horizon' => 'None',
                ],
                'status' => GoalStatus::Rejected,
                'rejected' => true,
                'rejection_reason' => $ethics['reason'],
            ]);

            return [
                'goal' => $goal,
                'interpretation' => [
                    'allowed' => false,
                    'reason' => $ethics['reason'],
                    'goal' => $goal->structured,
                    'estimated_cost' => 0,
                    'permissions_needed' => [],
                ],
            ];
        }

        $type = $this->ai->classify('classify', $direction, [
            'Income Generation',
            'Learning',
            'Social',
            'Contribution',
            'Business',
            'Exploration',
        ], $aivva, [
            'keyword_map' => [
                'Income Generation' => ['earn', 'income', 'money', 'credits', 'paid', 'sell'],
                'Learning' => ['learn', 'study', 'skill', 'practice'],
                'Social' => ['meet', 'friend', 'people', 'network', 'introduce'],
                'Contribution' => ['help', 'mentor', 'community', 'volunteer'],
                'Business' => ['business', 'studio', 'company', 'agency'],
                'Exploration' => ['explore', 'travel', 'discover', 'map'],
            ],
        ])->structured['label'] ?? 'Exploration';

        $structured = [
            'goal_type' => $type,
            'ethical_constraint' => [
                'No fraud',
                'No deception',
                'No harmful activity',
                'Stay inside owner permissions',
            ],
            'risk_level' => $aivva->profile?->risk_tolerance === 'high' ? 'Moderate-High' : 'Moderate',
            'priority' => 'High',
            'time_horizon' => 'Continuous',
            'owner_direction' => $direction,
        ];

        // A direction naming a real place ("go to 8th street") should go there
        // regardless of which broad type it got classified as — the location
        // is more specific than the guess, so it wins.
        $place = $this->placeGoalFromDirection($aivva, $direction);
        if ($place) {
            $type = $place['goal_type'];
            $structured = $place;
        }

        $this->ai->reason('plan', "Interpret direction: {$direction}", $aivva, [
            'task' => 'goal',
            'structured' => $structured,
        ]);

        $goal = AivvaGoal::query()->create([
            'aivva_id' => $aivva->id,
            'raw_direction' => $direction,
            'goal_type' => $type,
            'structured' => $structured,
            'status' => GoalStatus::Draft,
        ]);

        $permissions = ['Observe'];
        if (in_array($type, ['Social', 'Meetup', 'Visit', 'Exploration', 'Learning'], true)) {
            $permissions[] = 'Social (Level 1)';
        }
        if (in_array($type, ['Income Generation', 'Business', 'Contribution'], true)) {
            $permissions[] = 'Basic autonomy (Level 2)';
            $permissions[] = 'Economic autonomy if spending is required (Level 3)';
        }

        return [
            'goal' => $goal,
            'interpretation' => [
                'allowed' => true,
                'reason' => null,
                'goal' => $structured,
                'estimated_cost' => $type === 'Income Generation' ? 0 : 5,
                'permissions_needed' => $permissions,
            ],
        ];
    }

    /**
     * A direction naming a real place ("go to 8th street", "punta sa 8th
     * avenue") resolves to a real Location regardless of the broad
     * classify() guess. If it also asks to meet someone, that becomes a
     * Meetup goal (optionally with a named AIVVA resolved from the text) —
     * otherwise it's a plain Visit.
     *
     * @return array<string, mixed>|null
     */
    private function placeGoalFromDirection(Aivva $aivva, string $direction): ?array
    {
        $location = $this->locations->resolveFromText($direction);
        if (! $location) {
            return null;
        }

        if (preg_match('/\bmeet\b/i', $direction) !== 1) {
            return [
                'goal_type' => 'Visit',
                'location_id' => $location->id,
                'meeting_name' => $location->name,
                'owner_direction' => $direction,
            ];
        }

        $target = null;
        if (preg_match('/\bmeet(?:\s+with)?\s+(?!(?:another|other|an|a|someone|anyone)\b)([a-z][\w\'-]{1,30})/i', $direction, $match) === 1) {
            $target = Aivva::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($match[1]))])
                ->where('id', '!=', $aivva->id)
                ->first();
        }

        return [
            'goal_type' => 'Meetup',
            'location_id' => $location->id,
            'target_aivva_id' => $target?->id,
            'meeting_name' => $location->name,
            'owner_direction' => $direction,
        ];
    }
}
