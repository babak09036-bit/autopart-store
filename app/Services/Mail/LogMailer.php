<?php
declare(strict_types=1);

namespace App\Services\Mail;

/** Development mailer: nothing is sent; the message is appended to storage/logs/mail-YYYY-MM-DD.log. */
final class LogMailer implements MailerInterface
{
    public function send(string $to, string $subject, string $text): bool
    {
        $dir = BASE_DIR . '/storage/logs';
        $entry = sprintf("[%s] TO: %s\nSUBJECT: %s\n%s\n----\n", gmdate('Y-m-d H:i:s'), $to, $subject, $text);
        return (bool) @file_put_contents($dir . '/mail-' . gmdate('Y-m-d') . '.log', $entry, FILE_APPEND | LOCK_EX);
    }

    public static function make(): MailerInterface
    {
        // Extension point: return another MailerInterface based on config('app.mail.driver').
        return new self();
    }
}
