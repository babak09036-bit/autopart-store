<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\AdminAuth;
use App\Core\Database;
use App\Core\Logger;

/** Writes admin_activity_logs (admin actions) and audit_logs (system/customer events). */
final class ActivityLogger
{
    public static function admin(string $action, string $entityType, ?int $entityId, string $description, ?array $changes = null): void
    {
        Database::insert('admin_activity_logs', [
            'admin_user_id' => AdminAuth::id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => mb_substr($description, 0, 255),
            'changes' => $changes === null ? null : json_encode(Logger::mask($changes), JSON_UNESCAPED_UNICODE),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
        ]);
    }

    public static function audit(string $actorType, ?int $actorId, string $event, string $entityType, ?int $entityId, ?array $data = null): void
    {
        Database::insert('audit_logs', [
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'event' => $event,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'data' => $data === null ? null : json_encode(Logger::mask($data), JSON_UNESCAPED_UNICODE),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }

    /** Returns only changed keys as [field => [old, new]]. */
    public static function diff(array $before, array $after): array
    {
        $changes = [];
        foreach ($after as $key => $value) {
            $old = $before[$key] ?? null;
            // "1850000.00" from the database equals 1850000 from the form
            $sameNumber = is_numeric($old) && is_numeric($value) && (float) $old === (float) $value;
            if (!$sameNumber && (string) json_encode($old) !== (string) json_encode($value) && (string) $old !== (string) $value) {
                $changes[$key] = [$old, $value];
            }
        }
        return $changes;
    }
}
