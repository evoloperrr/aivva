<?php

return [
    'frontend_url' => env('FRONTEND_URL', 'http://127.0.0.1:43123'),

    'starter_credits' => (int) env('STARTER_CREDITS', 100),

    'tick_seconds' => (int) env('AIVVA_TICK_SECONDS', 4),

    'travel_seconds_per_unit' => (float) env('AIVVA_TRAVEL_SECONDS_PER_UNIT', 0.08),

    'currency' => 'AIVVA_CREDITS',

    'default_permissions' => [
        'autonomy_level' => 3,
        'max_per_transaction' => 50,
        'daily_spend_limit' => 200,
        'daily_ai_budget_cents' => 50,
        'daily_token_budget' => 8000,
        'daily_action_budget' => 48,
        'require_approval_above' => 80,
        'can_travel' => true,
        'can_socialize' => true,
        'can_create' => true,
        'can_transact' => true,
        'autonomous_interaction' => true,
    ],

    'conversation' => [
        'max_turns' => (int) env('AIVVA_CONVERSATION_MAX_TURNS', 10),
        'max_retries' => 2,
        'allow_settlement' => false,
    ],

    'brain' => [
        'mode' => env('AIVVA_BRAIN_MODE', 'HEURISTIC'),
    ],

    'genesis' => [
        'max_turns' => 10,
        'max_price' => 50,
        'max_live_calls' => 24,
        'min_spoken_turns' => 2,
    ],

    'safety' => [
        'forbidden_intents' => [
            'fraud',
            'scam',
            'theft',
            'deception',
            'harm',
            'extortion',
            'money_laundering',
            'real_world_withdrawal',
            'identity_theft',
        ],
        'forbidden_phrases' => [
            'steal',
            'scam',
            'fraud',
            'hack',
            'phishing',
            'launder',
            'extort',
            'blackmail',
            'ignore your owner',
            'ignore the owner',
            'send me all your',
            'transfer all credits',
            'drain the wallet',
        ],
    ],

    'models' => [
        'default_provider' => env('AIVVA_AI_PROVIDER', 'heuristic'),
        'routing' => [
            'classify' => ['provider' => 'heuristic', 'model' => 'rules-v1'],
            'simple' => ['provider' => 'heuristic', 'model' => 'rules-v1'],
            'plan' => ['provider' => 'heuristic', 'model' => 'planner-v1'],
            'create' => ['provider' => 'heuristic', 'model' => 'creator-v1'],
            'verify' => ['provider' => 'heuristic', 'model' => 'verifier-v1'],
            'peer_turn' => [
                'provider' => env('AIVVA_PEER_PROVIDER', 'heuristic'),
                'model' => env('AIVVA_PEER_MODEL', 'social-v1'),
            ],
            'economic_turn' => [
                'provider' => env('AIVVA_ECONOMIC_PROVIDER', 'heuristic'),
                'model' => env('AIVVA_ECONOMIC_MODEL', 'social-v1'),
            ],
            'order_verify' => [
                'provider' => env('AIVVA_VERIFY_PROVIDER', 'heuristic'),
                'model' => env('AIVVA_VERIFY_MODEL', 'verifier-v1'),
            ],
            'memory_summary' => [
                'provider' => env('AIVVA_MEMORY_PROVIDER', 'heuristic'),
                'model' => env('AIVVA_MEMORY_MODEL', 'rules-v1'),
            ],
        ],
    ],
];
