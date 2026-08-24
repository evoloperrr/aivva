<?php

namespace App\Domain\Agent;

use App\Ai\AiOrchestrator;
use App\Enums\ActionType;
use App\Models\Aivva;
use App\Models\AivvaGoal;
use App\Models\AivvaPlan;

class Planner
{
    public function __construct(
        private readonly AiOrchestrator $ai,
    ) {}

    public function createPlan(Aivva $aivva, AivvaGoal $goal): AivvaPlan
    {
        $type = $goal->goal_type ?? 'Exploration';
        $skills = collect($aivva->profile?->skills ?? [])->map(fn ($s) => mb_strtolower((string) $s));
        $creative = $skills->contains(fn ($s) => str_contains($s, 'music') || str_contains($s, 'art') || str_contains($s, 'writ') || str_contains($s, 'design'));

        $steps = match ($type) {
            'Income Generation' => $this->incomeSteps($creative),
            'Learning' => $this->simpleSteps([
                [ActionType::AnalyzeSkills, 'Review current skills and gaps'],
                [ActionType::Travel, 'Visit the Learning Campus'],
                [ActionType::Reflect, 'Record what to practice next'],
            ]),
            'Social' => $this->simpleSteps([
                [ActionType::Travel, 'Visit the Social Gardens'],
                [ActionType::Contact, 'Introduce yourself to a nearby AIVVA'],
                [ActionType::Reflect, 'Remember who you met'],
            ]),
            'Contribution' => $this->simpleSteps([
                [ActionType::ResearchMarket, 'Look for people who need help'],
                [ActionType::Contact, 'Offer unpaid help within permissions'],
                [ActionType::Reflect, 'Store what was useful'],
            ]),
            'Business' => $this->incomeSteps($creative),
            'Meetup' => $this->meetupSteps($goal->structured ?? []),
            default => $this->simpleSteps([
                [ActionType::Travel, 'Walk the city and observe'],
                [ActionType::ResearchMarket, 'Notice what the city needs'],
                [ActionType::Reflect, 'Decide a more specific direction'],
            ]),
        };

        $this->ai->reason('plan', "Create plan for {$type}: {$goal->raw_direction}", $aivva, [
            'task' => 'plan',
            'structured' => ['steps' => $steps],
        ]);

        return AivvaPlan::query()->create([
            'aivva_id' => $aivva->id,
            'goal_id' => $goal->id,
            'steps' => $steps,
            'current_step' => 0,
            'status' => 'ACTIVE',
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function incomeSteps(bool $creative): array
    {
        return $this->simpleSteps([
            [ActionType::AnalyzeSkills, 'Check existing skills and what they can ethically offer'],
            [ActionType::Travel, $creative ? 'Travel to the Creative District' : 'Travel to the Marketplace'],
            [ActionType::ResearchMarket, 'Search marketplace demand'],
            [ActionType::FindOpportunity, 'Select the best open request that matches skills'],
            [ActionType::Contact, 'Contact the requesting AIVVA with a structured offer'],
            [ActionType::CreateContent, 'Create the original work'],
            [ActionType::Negotiate, 'Agree a fair price inside both budgets'],
            [ActionType::DeliverWork, 'Deliver through escrow and settle'],
            [ActionType::Reflect, 'Remember what worked and look for the next opportunity'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $structured
     * @return list<array<string, mixed>>
     */
    private function meetupSteps(array $structured): array
    {
        $locationId = $structured['location_id'] ?? null;
        $targetId = $structured['target_aivva_id'] ?? null;
        $name = $structured['meeting_name'] ?? 'the meeting point';

        return [
            [
                'index' => 0,
                'type' => ActionType::Travel->value,
                'title' => "Travel to {$name}",
                'status' => 'PENDING',
                'payload' => array_filter(['location_id' => $locationId]),
            ],
            [
                'index' => 1,
                'type' => ActionType::Contact->value,
                'title' => 'Meet the other AIVVA there',
                'status' => 'PENDING',
                'payload' => array_filter(['target_aivva_id' => $targetId, 'peer' => true]),
            ],
            [
                'index' => 2,
                'type' => ActionType::Reflect->value,
                'title' => 'Remember the meeting',
                'status' => 'PENDING',
                'payload' => [],
            ],
        ];
    }

    /**
     * @param  list<array{0: ActionType, 1: string}>  $pairs
     * @return list<array<string, mixed>>
     */
    private function simpleSteps(array $pairs): array
    {
        return collect($pairs)->values()->map(fn ($pair, $index) => [
            'index' => $index,
            'type' => $pair[0]->value,
            'title' => $pair[1],
            'status' => 'PENDING',
        ])->all();
    }
}
