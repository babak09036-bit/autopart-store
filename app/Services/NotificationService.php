<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class NotificationService
{
    /** Broadcast to every admin (recipient_id NULL). */
    public static function admins(string $type, string $title, ?string $body = null, ?string $link = null): void
    {
        Database::insert('notifications', [
            'recipient_type' => 'admin', 'recipient_id' => null, 'type' => $type,
            'title' => mb_substr($title, 0, 190), 'body' => $body ? mb_substr($body, 0, 500) : null, 'link' => $link,
        ]);
    }

    public static function user(int $userId, string $type, string $title, ?string $body = null, ?string $link = null): void
    {
        Database::insert('notifications', [
            'recipient_type' => 'user', 'recipient_id' => $userId, 'type' => $type,
            'title' => mb_substr($title, 0, 190), 'body' => $body ? mb_substr($body, 0, 500) : null, 'link' => $link,
        ]);
    }
}
