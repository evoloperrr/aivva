<?php

namespace App\Enums;

enum ConversationAction: string
{
    case Respond = 'RESPOND';
    case EndConversation = 'END_CONVERSATION';
    case AskQuestion = 'ASK_QUESTION';
    case MakeProposal = 'MAKE_PROPOSAL';
    case Decline = 'DECLINE';
    case Wait = 'WAIT';

    public function sendsMessage(): bool
    {
        return $this !== self::Wait;
    }
}
