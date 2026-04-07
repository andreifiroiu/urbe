<?php

declare(strict_types=1);

namespace App\Services\Recommendation;

use App\Models\UserEventReaction;
use App\Services\InterestProfile\ProfileUpdater;
use Illuminate\Support\Facades\Log;

class FeedbackProcessor
{
    public function __construct(
        private readonly ProfileUpdater $profileUpdater,
    ) {}

    /**
     * Process a single reaction: update the user's profile and mark processed.
     */
    public function processReaction(UserEventReaction $reaction): void
    {
        if ($reaction->is_processed) {
            return;
        }

        $reaction->loadMissing(['user', 'event']);

        $this->profileUpdater->updateFromFeedback(
            $reaction->user,
            $reaction->event,
            $reaction->reaction->value,
        );

        $reaction->update(['is_processed' => true]);

        Log::debug('Processed feedback', [
            'reaction' => $reaction->reaction->value,
            'user_id' => $reaction->user_id,
            'event_id' => $reaction->event_id,
        ]);
    }

    /**
     * Process all unprocessed reactions and return how many were handled.
     */
    public function processUnprocessed(): int
    {
        $reactions = UserEventReaction::where('is_processed', false)
            ->with(['user', 'event'])
            ->get();

        $count = 0;

        foreach ($reactions as $reaction) {
            try {
                $this->processReaction($reaction);
                $count++;
            } catch (\Throwable $e) {
                Log::error('Failed to process reaction', [
                    'reaction_id' => $reaction->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info("Processed {$count} reactions");

        return $count;
    }
}
