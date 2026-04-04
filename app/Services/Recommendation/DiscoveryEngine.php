<?php

declare(strict_types=1);

namespace App\Services\Recommendation;

use App\Models\DiscoveryLog;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Collection;

class DiscoveryEngine
{
    /**
     * Surfaces novel and unexpected events to expand user horizons beyond
     * their established preferences. Targets event categories where the user
     * has low interest scores, creating opportunities for serendipitous discovery.
     */
    public function __construct() {}

    /**
     * Select discovery events for a user from categories they rarely engage with.
     *
     * Identifies the user's weakest interest categories and selects random
     * upcoming events from those categories. Logs each discovery selection
     * for tracking and analysis.
     *
     * @param User $user The user to discover events for.
     * @param int $count The number of discovery events to return.
     * @return Collection<int, Event> The selected discovery events.
     */
    public function discoverForUser(User $user, int $count = 2): Collection
    {
        // TODO: Get user's interest_profile as an associative array
        // TODO: Get all valid EventCategory values
        // TODO: Find categories where the user's score is below config('eventpulse.discovery.low_score_threshold', 0.3)
        //       Include categories not present in the profile at all (implicit score 0.0)
        // TODO: If no low-score categories found, use all categories (user has broad interests)
        // TODO: Query upcoming events from those low-score categories that the user hasn't reacted to
        //       Event::upcoming()
        //         ->whereIn('category', $lowScoreCategories)
        //         ->whereDoesntHave('reactions', fn($q) => $q->where('user_id', $user->id))
        //         ->inRandomOrder()
        //         ->limit($count)
        //         ->get()
        // TODO: For each selected event, create a DiscoveryLog record:
        //       DiscoveryLog::create([
        //           'user_id' => $user->id,
        //           'event_id' => $event->id,
        //           'surprise_score' => $this->calculateSurpriseScore($user, $event),
        //           'reason' => 'low_category_score',
        //       ])
        // TODO: Return the selected events
        return collect();
    }

    /**
     * Calculate how surprising/novel an event is for a given user.
     *
     * Returns a value between 0.0 (not surprising at all, matches preferences)
     * and 1.0 (maximally surprising, completely outside preferences).
     *
     * @param User $user The user to evaluate surprise for.
     * @param Event $event The event to evaluate.
     * @return float The surprise score between 0.0 and 1.0.
     */
    public function calculateSurpriseScore(User $user, Event $event): float
    {
        // TODO: Get the user's interest profile
        // TODO: Get the event's category value as a string
        // TODO: Look up the user's score for this category: $profile[$category] ?? 0.0
        // TODO: Surprise is the inverse of familiarity: 1.0 - $categoryScore
        // TODO: Clamp the result to [0.0, 1.0] using max(0.0, min(1.0, $surprise))
        // TODO: Return the surprise score
        return 0.0;
    }
}
