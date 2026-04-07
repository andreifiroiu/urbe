<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Reaction;
use App\Jobs\ProcessFeedbackJob;
use App\Models\Event;
use App\Models\User;
use App\Models\UserEventReaction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EmailReactionController extends Controller
{
    /**
     * Handle a reaction click from a signed email URL.
     *
     * The URL is pre-signed so no authentication is required, but the
     * signature is validated by the middleware.
     */
    public function store(Request $request, User $user, Event $event, string $reaction): Response
    {
        $reactionEnum = Reaction::tryFrom($reaction);

        if ($reactionEnum === null) {
            abort(404, 'Invalid reaction type.');
        }

        $userReaction = UserEventReaction::updateOrCreate(
            [
                'user_id' => $user->id,
                'event_id' => $event->id,
            ],
            [
                'reaction' => $reactionEnum,
            ],
        );

        ProcessFeedbackJob::dispatch($userReaction->id);

        return response()->view('emails.reaction-confirmed', [
            'event' => $event,
            'reaction' => $reactionEnum,
        ]);
    }
}
