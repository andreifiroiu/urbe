<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\NotificationChannel;
use App\Enums\NotificationFrequency;
use App\Http\Requests\NotificationSettingsRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationSettingsController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render('Settings/Notifications', [
            'user' => new UserResource($request->user()),
            'channels' => array_column(NotificationChannel::cases(), 'value'),
            'frequencies' => array_column(NotificationFrequency::cases(), 'value'),
        ]);
    }

    public function update(NotificationSettingsRequest $request): RedirectResponse
    {
        /** @var array{channel: string, frequency: string} $validated */
        $validated = $request->validated();

        $request->user()->update([
            'notification_channel' => NotificationChannel::from($validated['channel']),
            'notification_frequency' => NotificationFrequency::from($validated['frequency']),
        ]);

        return redirect()->route('profile.show')
            ->with('success', 'Notification settings updated.');
    }
}
