<?php

declare(strict_types=1);

namespace App\Services\Recommendation;

use App\Models\Event;
use Illuminate\Support\Collection;

class DiversityFilter
{
    /**
     * Ensures category diversity in recommendation sets by preventing
     * any single category from dominating the results. Applies a cap
     * per category and interleaves events from different categories
     * to create a balanced and varied recommendation list.
     */
    public function __construct() {}

    /**
     * Filter a scored collection of events to ensure category diversity.
     *
     * Groups events by category, caps each group to a maximum count,
     * and interleaves them so the final list alternates between categories
     * rather than clustering same-category events together.
     *
     * @param Collection<int, Event> $events The scored events sorted by relevance score descending.
     * @param int $maxPerCategory The maximum number of events allowed per category.
     * @return Collection<int, Event> The filtered and interleaved events.
     */
    public function filter(Collection $events, int $maxPerCategory = 3): Collection
    {
        // TODO: Group events by their category value: $events->groupBy(fn($e) => $e->category?->value ?? 'other')
        // TODO: For each category group, take only the first $maxPerCategory events (already sorted by score)
        // TODO: Interleave the groups: round-robin through categories, picking one event from each in turn
        //       This prevents same-category events from clustering in the output
        // TODO: Example interleave algorithm:
        //   TODO: Initialize empty result collection
        //   TODO: While any group still has events:
        //     TODO: For each category group with remaining events:
        //       TODO: Shift the first event from the group and add to result
        // TODO: Return the interleaved collection
        return collect();
    }
}
