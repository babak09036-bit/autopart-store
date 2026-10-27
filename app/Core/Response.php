<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    private array $headers = [];

    public function __construct(
        private string $body = '',
        private int $status = 200,
        array $headers = [],
    ) {
        $this->headers = $headers;
    }

    public static function json(array $payload, int $status = 200): self
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        return new self((string) $body, $status, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** Standard success envelope: { success, message, data }. */
    public static function ok(mixed $data = null, string $message = 'عملیات با موفقیت انجام شد', int $status = 200): self
    {
        return self::json(['success' => true, 'message' => $message, 'data' => $data ?? new \stdClass()], $status);
    }

    /** Standard error envelope: { success, message, errors }. */
    public static function error(string $message, int $status = 400, array $errors = []): self
    {
        return self::json(['success' => false, 'message' => $message, 'errors' => $errors ?: new \stdClass()], $status);
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($html, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function text(string $text, string $type = 'text/plain', int $status = 200): self
    {
        return new self($text, $status, ['Content-Type' => $type . '; charset=utf-8']);
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            $defaults = [
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'SAMEORIGIN',
                'Referrer-Policy' => 'strict-origin-when-cross-origin',
                'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            ];
            foreach ($this->headers + $defaults as $name => $value) {
                header($name . ': ' . $value);
            }
        }
        echo $this->body;
    }
}
