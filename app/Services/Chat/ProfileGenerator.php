<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\User;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Log;

class ProfileGenerator
{
    /**
     * Generates a structured interest profile from onboarding chat transcripts.
     * Uses Claude to analyze the full conversation and extract a JSON profile
     * mapping categories and tags to interest scores between 0.0 and 1.0.
     *
     * @param HttpClient $http The HTTP client for making Claude API requests.
     */
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * Generate a structured interest profile from a user's onboarding chat history.
     *
     * Loads all onboarding messages, sends them to Claude with a profile generation
     * prompt, and returns the parsed interest profile as an associative array.
     *
     * @param User $user The user whose onboarding chat should be analyzed.
     * @return array<string, float> The generated interest profile mapping categories/tags to scores.
     *   Example: ['music' => 0.9, 'jazz' => 0.85, 'technology' => 0.3, 'outdoor' => 0.7]
     *
     * @throws \RuntimeException If the Claude API call fails or returns unparseable output.
     */
    public function generateFromChat(User $user): array
    {
        // TODO: Load all onboarding chat messages for the user, ordered chronologically
        //       $messages = $user->chatMessages()->where('context', 'onboarding')->orderBy('created_at')->get()
        // TODO: Format the conversation as a readable transcript for Claude
        //       "User: {message}\nAssistant: {message}\n..."
        // TODO: Build the profile generation prompt from config('eventpulse.prompts.profile_generation')
        //       The prompt should instruct Claude to:
        //       - Analyze the conversation for expressed interests and preferences
        //       - Map interests to EventCategory enum values and freeform tags
        //       - Assign scores from 0.0 (no interest) to 1.0 (strong interest)
        //       - Include negative signals (things user explicitly dislikes get low scores)
        //       - Return JSON: { "category_name": score, "tag_name": score, ... }
        // TODO: Send POST request to Claude API with the transcript and prompt
        // TODO: Parse the JSON response
        // TODO: Validate all values are floats between 0.0 and 1.0
        // TODO: Clamp any out-of-range values to [0.0, 1.0]
        // TODO: Log token usage for cost tracking
        // TODO: Return the profile array
        return [];
    }

    /**
     * Merge two interest profiles, averaging scores for overlapping keys.
     *
     * Used when updating an existing profile with new information from
     * a profile update conversation. New keys are added directly; existing
     * keys get their scores averaged with the new values.
     *
     * @param array<string, float> $existing The current interest profile.
     * @param array<string, float> $new The new profile data to merge in.
     * @return array<string, float> The merged profile with all scores clamped to [0.0, 1.0].
     */
    public function mergeProfiles(array $existing, array $new): array
    {
        // TODO: Start with a copy of the existing profile
        // TODO: For each key in the new profile:
        //   TODO: If key exists in existing, average the two scores: ($existing[$key] + $new[$key]) / 2
        //   TODO: If key does not exist in existing, add it directly
        // TODO: Clamp all values to [0.0, 1.0] using max(0.0, min(1.0, $value))
        // TODO: Return the merged profile
        return [];
    }
}
