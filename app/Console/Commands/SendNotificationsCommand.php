<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Notification\NotificationComposer;
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Console\Command;

class SendNotificationsCommand extends Command
{
    protected $signature = 'eventpulse:send-notifications {--user= : Send to a specific user UUID}';

    protected $description = 'Compose and send event notification digests to users';

    public function handle(NotificationComposer $composer, NotificationDispatcher $dispatcher): int
    {
        $userId = $this->option('user');

        if ($userId) {
            $user = User::findOrFail($userId);
            $this->info("Composing notification for user: {$user->email}");

            $notification = $composer->composeForUser($user);
            $dispatcher->dispatch($notification);

            $this->info('Notification sent successfully.');

            return self::SUCCESS;
        }

        $this->info('Composing notifications for all due users...');

        $notifications = $composer->composeForDueUsers();

        if ($notifications->isEmpty()) {
            $this->info('No users are due for notifications.');

            return self::SUCCESS;
        }

        $this->info("Dispatching {$notifications->count()} notifications...");

        foreach ($notifications as $notification) {
            $dispatcher->dispatch($notification);
        }

        $this->info("Successfully sent {$notifications->count()} notifications.");

        return self::SUCCESS;
    }
}
