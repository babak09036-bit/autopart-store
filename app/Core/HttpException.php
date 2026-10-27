<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** An error that is safe to show to the client, with an HTTP status and optional field errors. */
class HttpException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        string $message,
        public readonly array $errors = [],
    ) {
        parent::__construct($message, $status);
    }
}
