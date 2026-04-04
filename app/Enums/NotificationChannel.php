<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationChannel: string
{
    case Email = 'email';
    case Push = 'push';
    case Both = 'both';
}
