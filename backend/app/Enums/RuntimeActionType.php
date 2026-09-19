<?php

namespace App\Enums;

enum RuntimeActionType: string
{
    case Idle = 'IDLE';
    case MoveTo = 'MOVE_TO';
    case FaceTarget = 'FACE_TARGET';
    case Interact = 'INTERACT';
    case Say = 'SAY';
}
