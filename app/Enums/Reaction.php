<?php

declare(strict_types=1);

namespace App\Enums;

enum Reaction: string
{
    case Interested = 'interested';
    case NotInterested = 'not_interested';
    case Saved = 'saved';
    case Hidden = 'hidden';
    case LinkOpened = 'link_opened';
}
