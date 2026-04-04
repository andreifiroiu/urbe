<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\User;
use App\Services\Anthropic\AnthropicClient;
use Illuminate\Support\Facades\Log;

class ProfileUpdateAgent
{
    public function __construct(
        private readonly AnthropicClient $client,
    ) {}

    /**
     * Process a user message in the profile-update chat and return the response.
     */
    public function respond(User $user, string $userMessage): string
    {
        $history = $user->chatMessages()
            ->where('context', 'profile_update')
            ->orderBy('created_at')
            ->limit(20)
            ->get();

        $messages = $history->map(fn ($msg) => [
            'role' => $msg->role,
            'content' => $msg->content,
        ])->toArray();

        $systemPrompt = 'You are EventPulse, helping the user refine their event preferences. '
            .'Their current profile: '.json_encode($user->interest_profile ?? []).'. '
            .'Understand what they want to change, confirm changes, then respond naturally.';

        try {
            $result = $this->client->sendMultiTurn(
                systemPrompt: $systemPrompt,
                messages: $messages,
                operation: 'profile_update_chat',
                logMetadata: ['user_id' => $user->id],
            );

            return $result['content'];
        } catch (\Throwable $e) {
            Log::error('Profile update chat failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return "I'm sorry, I had trouble processing that. Could you try again?";
        }
    }
}
