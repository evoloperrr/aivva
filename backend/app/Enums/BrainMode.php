<?php

namespace App\Enums;

enum BrainMode: string
{
    case Heuristic = 'HEURISTIC';
    case LiveLlm = 'LIVE_LLM';
    case AutoRouted = 'AUTO_ROUTED';
}
