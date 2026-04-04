<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Log;

class OnboardingAgent
{
    /**
     * Manages the conversational onboarding flow where new users describe
     * their event preferences through natural language chat. Uses Claude
     * as the conversational engine with a specialized system prompt that
     * guides users to express their interests in detail.
     *
     * @param HttpClient $http The HTTP client for making Claude API requests.
     */
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * Send a user message in the onboarding chat and return the assistant's response.
     *
     * Loads the user's conversation history, builds the messages array
     * for the Claude API, sends the request with the onboarding system prompt,
     * saves both messages to the database, and returns the assistant's reply.
     *
     * @param User $user The user participating in the onboarding chat.
     * @param string $message The user's message text.
     * @return string The assistant's response text.
     *
     * @throws \RuntimeException If the Claude API call fails.
     */
    public function chat(User $user, string $message): string
    {
        // TODO: Save the user's message to chat_messages table:
        //       ChatMessage::create(['user_id' => $user->id, 'role' => 'user', 'content' => $message])
        // TODO: Load full conversation history for this user:
        //       $user->chatMessages()->where('context', 'onboarding')->orderBy('created_at')->get()
        // TODO: Build the messages array for Claude API from conversation history:
        //       [['role' => $msg->role, 'content' => $msg->content], ...]
        // TODO: Load the onboarding system prompt from config('eventpulse.prompts.onboarding')
        //       The prompt should instruct Claude to:
        //       - Ask about event preferences, favorite activities, music genres, etc.
        //       - Probe for specific likes and dislikes (e.g., "I like jazz but not smooth jazz")
        //       - Ask about location preferences, willingness to travel
        //       - Ask about preferred times and price sensitivity
        //       - Be conversational, warm, and concise
        // TODO: Send POST request to Claude API:
        //       POST https://api.anthropic.com/v1/messages
        //       with model, max_tokens, system prompt, and messages array
        // TODO: Extract the assistant's response text from the API response
        // TODO: Save the assistant's response to chat_messages table:
        //       ChatMessage::create(['user_id' => $user->id, 'role' => 'assistant', 'content' => $response, 'context' => 'onboarding'])
        // TODO: Log token usage for cost tracking
        // TODO: Return the assistant's response text
        return '';
    }

    /**
     * Determine whether the onboarding conversation has gathered enough information.
     *
     * Checks if the user has exchanged enough messages for the system to
     * generate a meaningful interest profile.
     *
     * @param User $user The user to check onboarding status for.
     * @return bool True if onboarding has gathered sufficient information.
     */
    public function isOnboardingComplete(User $user): bool
    {
        // TODO: Count user messages (role = 'user') in the onboarding context
        //       $userMessageCount = $user->chatMessages()->where('context', 'onboarding')->where('role', 'user')->count()
        // TODO: Get minimum required exchanges from config('eventpulse.onboarding.min_exchanges', 4)
        // TODO: Return true if $userMessageCount >= $minExchanges
        return false;
    }
}
