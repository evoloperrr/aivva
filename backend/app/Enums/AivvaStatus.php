<?php

namespace App\Enums;

enum AivvaStatus: string
{
    case Dormant = 'DORMANT';
    case Idle = 'IDLE';
    case Thinking = 'THINKING';
    case Planning = 'PLANNING';
    case Traveling = 'TRAVELING';
    case Working = 'WORKING';
    case Creating = 'CREATING';
    case Socializing = 'SOCIALIZING';
    case Negotiating = 'NEGOTIATING';
    case WaitingApproval = 'WAITING_APPROVAL';
    case WaitingDelivery = 'WAITING_DELIVERY';
    case Learning = 'LEARNING';
    case Paused = 'PAUSED';
    case Error = 'ERROR';

    public function isActive(): bool
    {
        return ! in_array($this, [self::Dormant, self::Paused, self::Error], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Dormant => 'Dormant',
            self::Idle => 'Idle',
            self::Thinking => 'Thinking',
            self::Planning => 'Planning',
            self::Traveling => 'Traveling',
            self::Working => 'Working',
            self::Creating => 'Creating',
            self::Socializing => 'Socializing',
            self::Negotiating => 'Negotiating',
            self::WaitingApproval => 'Waiting for approval',
            self::WaitingDelivery => 'Waiting for delivery',
            self::Learning => 'Learning',
            self::Paused => 'Paused',
            self::Error => 'Needs attention',
        };
    }
}
