<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Realtime = 'realtime';
}
