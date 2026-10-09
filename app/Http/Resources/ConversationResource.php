<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'user' => [
                'id' => $this->user_id,
                'name' => $this->user?->name,
            ],
            'participants' => $this->whenLoaded('users', fn () => $this->users->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'read_at' => $user->pivot->read_at,
            ])),
            'latest_message' => $this->whenLoaded('latestMessage', fn () => $this->latestMessage
                ? new MessageResource($this->latestMessage)
                : null),
            'unread_count' => $this->when(isset($this->unread_count), fn () => (int) $this->unread_count),
            'last_message_at' => $this->last_message,
            'created_at' => $this->created_at,
        ];
    }
}
