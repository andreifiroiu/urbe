<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ChatRequest;
use App\Http\Resources\ChatMessageResource;
use App\Services\Chat\OnboardingAgent;
use App\Services\Chat\ProfileUpdateAgent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function __construct(
        private readonly OnboardingAgent $onboardingAgent,
        private readonly ProfileUpdateAgent $profileUpdateAgent,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $messages = $user->chatMessages()
            ->orderBy('created_at', 'asc')
            ->get();

        return Inertia::render('Onboarding/Chat', [
            'messages' => ChatMessageResource::collection($messages),
            'onboardingCompleted' => $user->onboarding_completed,
        ]);
    }

    public function store(ChatRequest $request): RedirectResponse
    {
        /** @var array{message: string, context?: string} $validated */
        $validated = $request->validated();

        $user = $request->user();
        $context = $validated['context'] ?? 'onboarding';

        $user->chatMessages()->create([
            'role' => 'user',
            'content' => $validated['message'],
            'context' => $context,
        ]);

        $agent = $context === 'profile_update'
            ? $this->profileUpdateAgent
            : $this->onboardingAgent;

        $response = $agent->respond($user, $validated['message']);

        $user->chatMessages()->create([
            'role' => 'assistant',
            'content' => $response,
            'context' => $context,
        ]);

        return redirect()->back();
    }
}
