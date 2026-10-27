<?php
declare(strict_types=1);

namespace App\Core;

/** Daily file logger in storage/logs. Sensitive keys are masked before writing. */
final class Logger
{
    private const SECRET_KEYS = ['password', 'password_confirmation', 'current_password', 'new_password', 'token', 'csrf', 'card', 'cvv', 'secret', 'authorization', 'cookie', 'db_password'];

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function write(string $level, string $message, array $context = []): void
    {
        $dir = BASE_DIR . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = sprintf("[%s] %s: %s %s\n", gmdate('Y-m-d H:i:s'), $level, $message,
            $context ? json_encode(self::mask($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '');
        @file_put_contents($dir . '/app-' . gmdate('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    public static function mask(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SECRET_KEYS, true)) {
                $data[$key] = '***';
            } elseif (is_array($value)) {
                $data[$key] = self::mask($value);
            }
        }
        return $data;
    }
}
