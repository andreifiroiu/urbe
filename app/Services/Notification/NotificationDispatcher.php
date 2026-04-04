<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Models\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationDispatcher
{
    /**
     * Dispatches composed notifications to users via their preferred channel.
     * Currently supports email delivery; renders the notification content
     * and sends it using Laravel's Mail facade, then records the sent timestamp.
     *
     * @param EmailRenderer $emailRenderer Renders notification data into HTML email content.
     */
    public function __construct(
        private readonly EmailRenderer $emailRenderer,
    ) {}

    /**
     * Dispatch a single notification to the user.
     *
     * Renders the email content, sends it via the Mail facade,
     * and updates the notification's sent_at timestamp.
     *
     * @param Notification $notification The notification to dispatch.
     * @return void
     *
     * @throws \RuntimeException If email rendering or sending fails.
     */
    public function dispatch(Notification $notification): void
    {
        // TODO: Render the email HTML using EmailRenderer::render($notification)
        // TODO: Update the notification's body_html with the rendered content
        // TODO: Load the notification's user for email address
        // TODO: Send the email using Mail::html($html, function ($message) { ... }):
        //   TODO: Set 'to' address from $notification->user->email
        //   TODO: Set 'subject' from $notification->subject
        //   TODO: Set 'from' from config('mail.from.address') and config('mail.from.name')
        // TODO: Update $notification->sent_at = now()
        // TODO: Save the notification
        // TODO: Log info: "Notification {id} sent to user {user_id} via email"
    }

    /**
     * Dispatch a batch of notifications.
     *
     * Sends each notification independently; failures for individual
     * notifications are logged but do not halt the batch.
     *
     * @param Collection<int, Notification> $notifications The notifications to dispatch.
     * @return int The number of notifications successfully sent.
     */
    public function dispatchBatch(Collection $notifications): int
    {
        // TODO: Initialize a counter for successfully sent notifications
        // TODO: For each notification in the collection:
        //   TODO: Call dispatch($notification) in a try/catch
        //   TODO: On success, increment counter
        //   TODO: On failure, log error with notification ID and exception message
        // TODO: Log summary: "Dispatched {count}/{total} notifications"
        // TODO: Return the count of successfully sent notifications
        return 0;
    }
}
