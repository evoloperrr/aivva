<?php

namespace App\Domain\Notifications;

use App\Models\AppNotification;
use App\Models\Aivva;

class NotificationService
{
    public function meaningful(Aivva $aivva, string $type, string $title, ?string $body = null, array $meta = []): AppNotification
    {
        return AppNotification::query()->create([
            'user_id' => $aivva->owner_id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'meta' => $meta,
        ]);
    }
}
