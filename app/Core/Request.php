<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    public array $params = [];
    private ?array $jsonBody = null;

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        private readonly array $post,
        public readonly array $files,
        private readonly array $server,
    ) {
    }

    public static function capture(): self
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = rawurldecode((string) parse_url($uri, PHP_URL_PATH));
        $base = (string) Config::get('app.base_path', '');
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        $path = '/' . trim(preg_replace('#/+#', '/', $path) ?? '', '/');

        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        return new self($method, $path, $_GET, $_POST, $_FILES, $_SERVER);
    }

    /** JSON body for application/json requests, otherwise form fields. */
    public function input(?string $key = null, mixed $default = null): mixed
    {
        $data = $this->all();
        if ($key === null) {
            return $data;
        }
        return $data[$key] ?? $default;
    }

    public function all(): array
    {
        if ($this->jsonBody === null) {
            $type = (string) ($this->server['CONTENT_TYPE'] ?? '');
            if (str_contains($type, 'application/json')) {
                $raw = file_get_contents('php://input') ?: '';
                if (strlen($raw) > 2_000_000) {
                    throw new HttpException(413, 'حجم درخواست بیش از حد مجاز است.');
                }
                $decoded = $raw === '' ? [] : json_decode($raw, true);
                if (!is_array($decoded)) {
                    throw new HttpException(400, 'بدنه درخواست JSON معتبر نیست.');
                }
                $this->jsonBody = $decoded;
            } else {
                $this->jsonBody = $this->post;
            }
        }
        return $this->jsonBody;
    }

    /** Returns only the listed keys (mass-assignment guard). */
    public function only(array $keys): array
    {
        $data = $this->all();
        return array_intersect_key($data, array_flip($keys));
    }

    public function query(string $key, mixed $default = null): mixed
    {
        $value = $this->query[$key] ?? $default;
        return is_array($value) ? $default : $value;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $this->server[$key] ?? null;
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return mb_substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function isApi(): bool
    {
        return str_starts_with($this->path, '/api/') || $this->path === '/api';
    }

    public function isSecure(): bool
    {
        return (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off')
            || ($this->server['SERVER_PORT'] ?? null) == 443;
    }

    public function param(string $key): ?string
    {
        return $this->params[$key] ?? null;
    }
}
