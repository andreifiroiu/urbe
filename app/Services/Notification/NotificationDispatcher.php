<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Models\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

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
        // Guard against duplicate sends (e.g. job retry after mail succeeded but DB update failed)
        if ($notification->sent_at !== null) {
            Log::info("Notification {$notification->id} already sent, skipping");

            return;
        }

        $html = $this->emailRenderer->render($notification);
        $notification->update(['body_html' => $html]);

        $notification->loadMissing('user');
        $user = $notification->user;

        try {
            Mail::html($html, function ($message) use ($user, $notification): void {
                $message->to($user->email)
                    ->subject($notification->subject ?? 'Your EventPulse Digest');
            });
        } catch (Throwable $e) {
            Log::error("Notification {$notification->id} mail send failed", ['error' => $e->getMessage()]);
            throw $e;
        }

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
            } catch (Throwable $e) {
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
