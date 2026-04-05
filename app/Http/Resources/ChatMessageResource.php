<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChatMessage
 */
class ChatMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'content' => trim(str_replace('[PROFILE_READY]', '', $this->content)),
            'context' => $this->context,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
