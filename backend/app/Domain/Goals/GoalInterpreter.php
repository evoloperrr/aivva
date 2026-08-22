<?php

namespace App\Domain\Goals;

use App\Ai\AiOrchestrator;
use App\Domain\Ethics\EthicsEngine;
use App\Enums\GoalStatus;
use App\Models\Aivva;
use App\Models\AivvaGoal;

class GoalInterpreter
{
    public function __construct(
        private readonly EthicsEngine $ethics,
        private readonly AiOrchestrator $ai,
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
        if (in_array($type, ['Social', 'Exploration', 'Learning'], true)) {
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
}
