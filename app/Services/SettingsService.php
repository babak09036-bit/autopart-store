<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/** Store settings from site_settings, typed and cached per request. */
final class SettingsService
{
    private static ?array $cache = null;

    public static function all(bool $publicOnly = false): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (Database::all('SELECT setting_key, value, type, is_public FROM site_settings ORDER BY group_name, sort_order') as $row) {
                self::$cache[$row['setting_key']] = ['value' => self::cast($row['type'], $row['value']), 'public' => (bool) $row['is_public']];
            }
        }
        $out = [];
        foreach (self::$cache as $key => $item) {
            if (!$publicOnly || $item['public']) {
                $out[$key] = $item['value'];
            }
        }
        return $out;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function forget(): void
    {
        self::$cache = null;
    }

    public static function cast(string $type, ?string $value): mixed
    {
        return match ($type) {
            'int' => (int) $value,
            'bool' => $value === '1',
            'json' => $value ? (json_decode($value, true) ?? []) : [],
            default => $value ?? '',
        };
    }
}
