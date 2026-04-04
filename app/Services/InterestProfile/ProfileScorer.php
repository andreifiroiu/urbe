<?php

declare(strict_types=1);

namespace App\Services\InterestProfile;

use App\Models\Event;
use App\Models\User;

class ProfileScorer
{
    /**
     * Calculate an overall relevance score for an event based on the user's profile.
     *
     * Combines category and tag scores using configured weights.
     */
    public function scoreForEvent(User $user, Event $event): float
    {
        $profile = $user->interest_profile ?? [];
        $weights = config('eventpulse.recommendation.weights');

        $categoryScore = $this->calculateCategoryScore($profile, $event->category->value);
        $tagScore = $this->calculateTagScore($profile, $event->tags ?? []);

        $catWeight = $weights['category'] ?? 0.3;
        $tagWeight = $weights['tags'] ?? 0.2;
        $totalWeight = $catWeight + $tagWeight;

        if ($totalWeight === 0.0) {
            return 0.0;
        }

        $score = (($catWeight * $categoryScore) + ($tagWeight * $tagScore)) / $totalWeight;

        return max(0.0, min(1.0, $score));
    }

    /**
     * Get the user's profile score for a specific category.
     *
     * @param array<string, float> $profile User's interest profile
     * @param string $category Event category name
     * @return float Score between 0.0 and 1.0
     */
    public function calculateCategoryScore(array $profile, string $category): float
    {
        return (float) ($profile[$category] ?? 0.0);
    }

    /**
     * Calculate average profile score across matching tags.
     *
     * For each tag in the event, look up the user's profile score for that tag.
     * Returns the average of all matching tag scores, or 0 if no tags match.
     *
     * @param array<string, float> $profile User's interest profile
     * @param array<int, string> $tags Event tags
     * @return float Average score between 0.0 and 1.0
     */
    public function calculateTagScore(array $profile, array $tags): float
    {
        if (empty($tags)) {
            return 0.0;
        }

        $scores = array_map(
            fn (string $tag) => (float) ($profile["tag:{$tag}"] ?? 0.0),
            $tags,
        );

        $sum = array_sum($scores);

        return max(0.0, min(1.0, $sum / count($scores)));
    }
}
