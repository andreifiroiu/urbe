<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Models\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationDispatcher
{
    public function __construct(
        private readonly EmailRenderer $emailRenderer,
    ) {}

    /**
     * Render, send, and record a single notification.
     */
    public function dispatch(Notification $notification): void
    {
        $html = $this->emailRenderer->render($notification);

        $notification->update(['body_html' => $html]);

        $notification->loadMissing('user');
        $user = $notification->user;

        Mail::html($html, function ($message) use ($user, $notification): void {
            $message->to($user->email)
                ->subject($notification->subject ?? 'Your EventPulse Digest');
        });

        $notification->update(['sent_at' => now()]);

        Log::info("Notification {$notification->id} sent to user {$user->id} via email");
    }

    /**
     * Dispatch a batch of notifications. Failures are logged, not thrown.
     *
     * @return int Number of successfully sent notifications.
     */
    public function dispatchBatch(Collection $notifications): int
    {
        $sent = 0;

        foreach ($notifications as $notification) {
            try {
                $this->dispatch($notification);
                $sent++;
            } catch (\Throwable $e) {
                Log::error('Failed to dispatch notification', [
                    'notification_id' => $notification->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info("Dispatched {$sent}/{$notifications->count()} notifications");

        return $sent;
    }
}
