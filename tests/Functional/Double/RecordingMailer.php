<?php

declare(strict_types=1);

namespace App\Tests\Functional\Double;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * Keeps every message it is asked to send, or throws the configured failure instead.
 */
final class RecordingMailer implements MailerInterface
{
    /** @var list<RawMessage> */
    public array $sent = [];

    public function __construct(private ?\Throwable $failure = null)
    {
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        if ($this->failure) {
            throw $this->failure;
        }

        $this->sent[] = $message;
    }
}
