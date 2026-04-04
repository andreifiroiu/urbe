<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Log;

class ProfileUpdateAgent
{
    /**
     * Handles post-onboarding conversational profile refinement. Users can
     * chat to adjust their preferences (e.g., "I'm getting too many tech events"
     * or "I want to see more outdoor activities"). The agent extracts structured
     * profile changes from the conversation and applies them.
     *
     * @param HttpClient $http The HTTP client for making Claude API requests.
     */
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * Send a user message in the profile update chat and return the assistant's response.
     *
     * Similar to OnboardingAgent::chat() but uses a profile-update-specific
     * system prompt that focuses on understanding what changes the user wants
     * to make to their existing preferences.
     *
     * @param User $user The user refining their profile.
     * @param string $message The user's message text.
     * @return string The assistant's response text.
     *
     * @throws \RuntimeException If the Claude API call fails.
     */
    public function chat(User $user, string $message): string
    {
        // TODO: Save the user's message to chat_messages table with context='profile_update'
        // TODO: Load recent conversation history for this user in the 'profile_update' context
        //       Limit to last N messages to keep context window manageable
        // TODO: Load the user's current interest_profile to include as context for Claude
        // TODO: Build the messages array from conversation history
        // TODO: Load system prompt from config('eventpulse.prompts.profile_update')
        //       The prompt should instruct Claude to:
        //       - Understand what the user wants to change about their recommendations
        //       - Confirm changes before applying them
        //       - Be helpful in suggesting adjustments
        //       - Respond naturally while gathering structured update intent
        // TODO: Send POST request to Claude API with system prompt and messages
        // TODO: Extract the assistant's response text
        // TODO: Save the assistant's response to chat_messages table with context='profile_update'
        // TODO: Log token usage for cost tracking
        // TODO: Return the assistant's response text
        return '';
    }

    /**
     * Extract structured profile update instructions from a conversation.
     *
     * Sends the conversation to Claude with a prompt that asks it to identify
     * specific profile changes (increase/decrease category scores, add/remove tags).
     *
     * @param string $conversation The full conversation text to analyze.
     * @return array<string, mixed> An array of profile changes, e.g.:
     *   [
     *     'increase' => ['music' => 0.2, 'outdoor' => 0.15],
     *     'decrease' => ['technology' => 0.3],
     *     'set' => ['jazz' => 0.9],
     *   ]
     */
    public function extractProfileUpdates(string $conversation): array
    {
        // TODO: Build a prompt instructing Claude to extract profile changes from the conversation
        //       Instruct Claude to respond with JSON: { "increase": {...}, "decrease": {...}, "set": {...} }
        // TODO: Send the request to Claude API
        // TODO: Parse the JSON response
        // TODO: Validate the structure:
        //   TODO: Each key ('increase', 'decrease', 'set') should map to an associative array
        //   TODO: Values should be floats between 0.0 and 1.0
        // TODO: On parse/validation failure, log warning and return empty array
        // TODO: Return the structured profile changes
        return [];
    }
}
