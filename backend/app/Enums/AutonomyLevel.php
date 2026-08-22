<?php

namespace App\Enums;

enum AutonomyLevel: int
{
    case Observe = 0;
    case Social = 1;
    case Basic = 2;
    case Economic = 3;
    case High = 4;

    public function label(): string
    {
        return match ($this) {
            self::Observe => 'Observe',
            self::Social => 'Social',
            self::Basic => 'Basic autonomy',
            self::Economic => 'Economic autonomy',
            self::High => 'High autonomy',
        };
    }

    public function canTalk(): bool
    {
        return $this->value >= self::Social->value;
    }

    public function canTravel(): bool
    {
        return $this->value >= self::Social->value;
    }

    public function canWork(): bool
    {
        return $this->value >= self::Basic->value;
    }

    public function canSpend(): bool
    {
        return $this->value >= self::Economic->value;
    }

    public function canOperateBusiness(): bool
    {
        return $this->value >= self::High->value;
    }
}
