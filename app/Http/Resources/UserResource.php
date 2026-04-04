<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'interest_profile' => $this->interest_profile,
            'discovery_openness' => $this->discovery_openness,
            'notification_channel' => $this->notification_channel?->value,
            'notification_frequency' => $this->notification_frequency?->value,
            'timezone' => $this->timezone,
            'city' => $this->city,
            'onboarding_completed' => $this->onboarding_completed,
        ];
    }
}
