<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Enums\NotificationChannel;
use App\Models\Notification;
use App\Models\User;
use App\Services\Recommendation\RecommendationEngine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class NotificationComposer
{
    /**
     * Composes notification records by generating personalized event recommendations
     * for users and packaging them into Notification models ready for dispatch.
     * Handles the logic of determining which users are due for notifications
     * and assembling the event lists with both recommendations and discovery events.
     *
     * @param RecommendationEngine $recommendationEngine Generates personalized event recommendations.
     */
    public function __construct(
        private readonly RecommendationEngine $recommendationEngine,
    ) {}

    /**
     * Compose a notification for a single user.
     *
     * Generates recommendations, builds a Notification model with event IDs
     * and discovery event IDs. Returns null if no suitable events are found.
     *
     * @param User $user The user to compose a notification for.
     * @return Notification|null The composed notification, or null if no events to recommend.
     */
    public function compose(User $user): ?Notification
    {
        // TODO: Get the number of events to include from config('eventpulse.notifications.events_per_notification', 10)
        // TODO: Generate recommendations using RecommendationEngine::recommend($user, $eventsCount)
        // TODO: If the recommendation batch has no recommended events and no discovery events, return null
        // TODO: Build a subject line for the notification (e.g., "Your EventPulse picks for {date}")
        // TODO: Create and return a Notification model (do not save yet):
        //       new Notification([
        //           'user_id' => $user->id,
        //           'channel' => $user->notification_channel ?? NotificationChannel::Email,
        //           'frequency' => $user->notification_frequency,
        //           'event_ids' => $batch->recommendedEventIds,
        //           'discovery_event_ids' => $batch->discoveryEventIds,
        //           'subject' => $subject,
        //       ])
        return null;
    }

    /**
     * Compose notifications for all users who are due to receive them.
     *
     * Determines which users should receive a notification based on their
     * notification frequency and when they last received one, then composes
     * a notification for each eligible user.
     *
     * @return Collection<int, Notification> The composed notifications ready for dispatch.
     */
    public function composeForAll(): Collection
    {
        // TODO: Query users who are due for a notification:
        //   TODO: Users with onboarding_completed = true
        //   TODO: Filter by notification_frequency vs. their last notification sent_at:
        //     TODO: 'daily' users: last notification was > 24 hours ago or never
        //     TODO: 'weekly' users: last notification was > 7 days ago or never
        //     TODO: 'biweekly' users: last notification was > 14 days ago or never
        //   TODO: Exclude users with notification_channel = 'none' (if such a value exists)
        // TODO: For each eligible user:
        //   TODO: Call compose($user) in a try/catch
        //   TODO: If result is not null, save the notification and add to results
        //   TODO: On failure, log error with user ID
        // TODO: Log summary: "Composed {count} notifications for {total_users} eligible users"
        // TODO: Return the collection of composed Notification models
        return collect();
    }
}
