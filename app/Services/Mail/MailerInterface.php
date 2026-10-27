<?php
declare(strict_types=1);

namespace App\Services\Mail;

/**
 * Outgoing mail abstraction. The project ships only LogMailer (writes the message to
 * storage/logs/mail-*.log). Add an SMTP or API-based implementation for real delivery
 * and select it with MAIL_DRIVER.
 */
interface MailerInterface
{
    public function send(string $to, string $subject, string $text): bool;
}
