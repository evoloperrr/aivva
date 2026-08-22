<?php

namespace App\Enums;

enum MemoryCategory: string
{
    case ShortTerm = 'SHORT_TERM';
    case LongTerm = 'LONG_TERM';
    case Relationship = 'RELATIONSHIP';
    case Economic = 'ECONOMIC';
    case Skill = 'SKILL';
    case Goal = 'GOAL';
}
