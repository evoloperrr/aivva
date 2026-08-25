<?php

namespace App\Domain\Notifications;

use App\Models\Aivva;
use App\Models\AppNotification;

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
