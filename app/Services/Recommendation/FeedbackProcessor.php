<?php

declare(strict_types=1);

namespace App\Services\Recommendation;

use App\Models\UserEventReaction;
use App\Services\InterestProfile\ProfileUpdater;
use Illuminate\Support\Facades\Log;

class FeedbackProcessor
{
    /**
     * Processes user feedback (reactions to events) and delegates profile
     * updates to the ProfileUpdater. Manages the feedback lifecycle by
     * tracking which reactions have been processed and ensuring each
     * reaction only influences the profile once.
     *
     * @param ProfileUpdater $profileUpdater Handles the actual profile score adjustments.
     */
    public function __construct(
        private readonly ProfileUpdater $profileUpdater,
    ) {}

    /**
     * Process a single user event reaction and update the user's interest profile.
     *
     * Delegates the profile update to ProfileUpdater with the appropriate
     * score delta based on the reaction type, then marks the reaction as processed.
     *
     * @param UserEventReaction $reaction The reaction to process.
     * @return void
     */
    public function processReaction(UserEventReaction $reaction): void
    {
        // TODO: Skip if reaction is already processed ($reaction->is_processed === true)
        // TODO: Load the related user and event (eager load if not already loaded)
        // TODO: Get the reaction type (interested, not_interested, saved, hidden, link_opened)
        // TODO: Delegate to ProfileUpdater::updateFromFeedback($user, $event, $reaction->reaction->value)
        // TODO: Mark the reaction as processed: $reaction->update(['is_processed' => true])
        // TODO: Log debug: "Processed {reaction_type} feedback from user {user_id} for event {event_id}"
    }

    /**
     * Process all unprocessed reactions in the system.
     *
     * Queries for reactions that have not yet been processed and applies
     * each one to update the corresponding user's interest profile.
     *
     * @return int The number of reactions that were processed.
     */
    public function processUnprocessed(): int
    {
        // TODO: Query UserEventReaction::where('is_processed', false)->with(['user', 'event'])->get()
        // TODO: Initialize a counter for processed reactions
        // TODO: For each unprocessed reaction:
        //   TODO: Call processReaction() in a try/catch
        //   TODO: On success, increment counter
        //   TODO: On failure, log error with reaction ID and exception message
        // TODO: Log info: "Processed {count} reactions"
        // TODO: Return the count of successfully processed reactions
        return 0;
    }
}
