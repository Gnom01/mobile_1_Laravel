<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PushNotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $recipients = $this->relationLoaded('recipients') ? $this->recipients : collect();
        $readRecipient = $recipients->first(fn ($recipient) => $recipient->read_at !== null);
        $openedRecipient = $recipients->first(fn ($recipient) => $recipient->opened_at !== null);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'category' => $this->category,
            'type' => $this->type,
            'priority' => $this->priority,
            'image_url' => $this->image_url,
            'deep_link' => $this->deep_link,
            'payload' => $this->payload_json,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'sent_at' => optional($this->sent_at)->toIso8601String(),
            'read_at' => optional($readRecipient ? $readRecipient->read_at : null)->toIso8601String(),
            'opened_at' => optional($openedRecipient ? $openedRecipient->opened_at : null)->toIso8601String(),
            'is_read' => (bool) $readRecipient,
        ];
    }
}
