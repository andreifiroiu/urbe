<?php

declare(strict_types=1);

namespace App\Services\InterestProfile;

use App\Models\Event;
use App\Models\User;

class ProfileUpdater
{
    /**
     * Update a user's interest profile based on their reaction to an event.
     *
     * Retrieves the feedback delta for the reaction type from config,
     * applies it to the event's category score and tag scores in the profile,
     * clamps all scores to [0.0, 1.0], and saves.
     */
    public function updateFromFeedback(User $user, Event $event, string $reaction): void
    {
        $deltas = config('eventpulse.feedback.deltas');
        $delta = $deltas[$reaction] ?? 0.0;

        if ($delta === 0.0) {
            return;
        }

        $profile = $user->interest_profile ?? [];

        // Update category score
        $categoryKey = $event->category->value;
        $currentCategoryScore = (float) ($profile[$categoryKey] ?? 0.0);
        $profile[$categoryKey] = $this->clampScore($currentCategoryScore + $delta);

        // Update tag scores
        foreach ($event->tags ?? [] as $tag) {
            $tagKey = "tag:{$tag}";
            $currentTagScore = (float) ($profile[$tagKey] ?? 0.0);
            $profile[$tagKey] = $this->clampScore($currentTagScore + ($delta * 0.5));
        }

        $user->update(['interest_profile' => $profile]);
    }

    /**
     * Clamp a score to the valid range [0.0, 1.0].
     */
    public function clampScore(float $score): float
    {
        return max(0.0, min(1.0, $score));
    }
}
