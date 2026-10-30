<?php

namespace App\Enums;

/**
 * The status shown to people: the saved status combined with today's date.
 */
enum ContractHealth: string
{
    case Draft = 'draft';
    case Active = 'active';
    case ActionSoon = 'action_soon';
    case Expired = 'expired';
    case Terminated = 'terminated';

    public const int ACTION_SOON_DAYS = 30;
}
