<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Models\Event;
use App\Models\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;

class EmailRenderer
{
    /**
     * Render a notification into an HTML email string.
     *
     * Loads events, generates signed reaction URLs, and renders the Blade template.
     */
    public function render(Notification $notification): string
    {
        $notification->loadMissing('user');
        $user = $notification->user;

        $recommendedEvents = Event::whereIn('id', $notification->event_ids ?? [])->get();
        $discoveryEvents = Event::whereIn('id', $notification->discovery_event_ids ?? [])->get();

        // Attach signed reaction URLs to each event
        $attachReactionUrls = function (Event $event) use ($user): array {
            return [
                'event' => $event,
                'reaction_urls' => [
                    'interested' => URL::signedRoute('reactions.email', [
                        'user' => $user->id,
                        'event' => $event->id,
                        'reaction' => 'interested',
                    ]),
                    'not_interested' => URL::signedRoute('reactions.email', [
                        'user' => $user->id,
                        'event' => $event->id,
                        'reaction' => 'not_interested',
                    ]),
                    'saved' => URL::signedRoute('reactions.email', [
                        'user' => $user->id,
                        'event' => $event->id,
                        'reaction' => 'saved',
                    ]),
                ],
            ];
        };

        $recommended = $recommendedEvents->map($attachReactionUrls)->toArray();
        $discovery = $discoveryEvents->map($attachReactionUrls)->toArray();

        return View::make('emails.digest', [
            'user' => $user,
            'recommendedEvents' => $recommended,
            'discoveryEvents' => $discovery,
            'subject' => $notification->subject,
        ])->render();
    }
}
