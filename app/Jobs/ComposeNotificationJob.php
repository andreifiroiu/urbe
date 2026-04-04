<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\Notification\NotificationComposer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ComposeNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public string $queue = 'notifications';

    public int $tries = 2;

    public function __construct(
        public readonly string $userId,
    ) {}

    public function handle(NotificationComposer $composer): void
    {
        $user = User::findOrFail($this->userId);

        $composer->compose($user);
    }
}
