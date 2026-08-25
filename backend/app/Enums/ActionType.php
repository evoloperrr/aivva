<?php

namespace App\Enums;

enum ActionType: string
{
    case AnalyzeSkills = 'ANALYZE_SKILLS';
    case InterpretGoal = 'INTERPRET_GOAL';
    case CreatePlan = 'CREATE_PLAN';
    case Travel = 'TRAVEL';
    case ResearchMarket = 'RESEARCH_MARKET';
    case FindOpportunity = 'FIND_OPPORTUNITY';
    case Contact = 'CONTACT';
    case SendMessage = 'SEND_MESSAGE';
    case CreateContent = 'CREATE_CONTENT';
    case CreateListing = 'CREATE_LISTING';
    case CreateRequest = 'CREATE_REQUEST';
    case SubmitOffer = 'SUBMIT_OFFER';
    case AcceptOffer = 'ACCEPT_OFFER';
    case Negotiate = 'NEGOTIATE';
    case DeliverWork = 'DELIVER_WORK';
    case GiveCredits = 'GIVE_CREDITS';
    case Reflect = 'REFLECT';
    case Rest = 'REST';
    case RecallHome = 'RECALL_HOME';
    case RejectUnsafe = 'REJECT_UNSAFE';

    public function statusWhileRunning(): AivvaStatus
    {
        return match ($this) {
            self::Travel, self::RecallHome => AivvaStatus::Traveling,
            self::CreateContent => AivvaStatus::Creating,
            self::Negotiate, self::SubmitOffer, self::AcceptOffer, self::GiveCredits => AivvaStatus::Negotiating,
            self::Contact, self::SendMessage => AivvaStatus::Socializing,
            self::Reflect, self::AnalyzeSkills, self::InterpretGoal => AivvaStatus::Learning,
            self::CreatePlan => AivvaStatus::Planning,
            self::RejectUnsafe => AivvaStatus::Idle,
            default => AivvaStatus::Working,
        };
    }
}
