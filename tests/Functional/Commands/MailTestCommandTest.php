<?php

declare(strict_types=1);

namespace App\Tests\Functional\Commands;

use App\Command\MailTestCommand;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * This command is the only way to exercise the mail settings without creating an event, so it has
 * to tell the two failures apart: an address that is not one, and a server that is not answering.
 */
class MailTestCommandTest extends KernelTestCase
{
    private function mailer(?\Throwable $failure = null): MailerInterface
    {
        return new class($failure) implements MailerInterface {
            public array $sent = [];

            public function __construct(private ?\Throwable $failure)
            {
            }

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                if ($this->failure) {
                    throw $this->failure;
                }

                $this->sent[] = $message;
            }
        };
    }

    private function tester(MailerInterface $mailer, ?string $inviteAddress = 'no-reply@example.org'): CommandTester
    {
        $application = new Application();
        $application->add(new MailTestCommand($mailer, $inviteAddress));

        return new CommandTester($application->find('davis:mail:test'));
    }

    public function testItSendsOneMessageFromTheConfiguredAddress(): void
    {
        $tester = $this->tester($mailer = $this->mailer());

        $this->assertSame(Command::SUCCESS, $tester->execute(['to' => 'someone@example.org']));
        $this->assertCount(1, $mailer->sent);

        $message = $mailer->sent[0];
        $this->assertSame('no-reply@example.org', $message->getFrom()[0]->getAddress());
        $this->assertSame('someone@example.org', $message->getTo()[0]->getAddress());
    }

    public function testAnAddressThatIsNotOneIsReportedAsSuch(): void
    {
        $tester = $this->tester($mailer = $this->mailer());

        $this->assertSame(Command::FAILURE, $tester->execute(['to' => 'not-an-address']));
        $this->assertStringContainsString('not-an-address', $tester->getDisplay());
        $this->assertSame([], $mailer->sent, 'Nothing should be handed to the transport');
    }

    public function testATransportThatIsNotAnsweringIsReportedAsSuch(): void
    {
        $tester = $this->tester($this->mailer(new TransportException('Connection refused')));

        $this->assertSame(Command::FAILURE, $tester->execute(['to' => 'someone@example.org']));

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Connection refused', $display);
        $this->assertStringContainsString('MAILER_DSN', $display, 'It should point at the setting to check');
    }

    /**
     * Without a sender address Davis sends no invitation at all, so testing the transport would
     * prove nothing.
     */
    public function testItRefusesToRunWithoutASenderAddress(): void
    {
        $tester = $this->tester($mailer = $this->mailer(), null);

        $this->assertSame(Command::FAILURE, $tester->execute(['to' => 'someone@example.org']));
        $this->assertStringContainsString('INVITE_FROM_ADDRESS', $tester->getDisplay());
        $this->assertSame([], $mailer->sent);
    }
}
