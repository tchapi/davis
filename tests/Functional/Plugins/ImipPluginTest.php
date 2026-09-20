<?php

declare(strict_types=1);

namespace App\Tests\Functional\Plugins;

use App\Plugins\DavisIMipPlugin;
use Psr\Log\AbstractLogger;
use Sabre\VObject\ITip;
use Sabre\VObject\Reader;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * Scheduling runs inside the PUT that stores the event. Anything thrown here reaches sabre and
 * comes back to the client as a 500, with the event never written — so the plugin has to handle
 * its own failures and record why.
 */
class ImipPluginTest extends KernelTestCase
{
    private const EVENT = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Davis//Test//EN\r\nBEGIN:VEVENT\r\nUID:imip-1\r\nDTSTAMP:20260101T100000Z\r\nDTSTART:20261201T100000Z\r\nDTEND:20261201T110000Z\r\nSUMMARY:Test meeting\r\nORGANIZER;CN=Organiser:mailto:organiser@example.org\r\nATTENDEE;CN=Attendee;PARTSTAT=NEEDS-ACTION:mailto:attendee@example.org\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

    private function message(string $recipient = 'mailto:attendee@example.org'): ITip\Message
    {
        $message = new ITip\Message();
        $message->method = 'REQUEST';
        $message->sequence = 1;
        $message->sender = 'mailto:organiser@example.org';
        $message->senderName = 'Organiser';
        $message->recipient = $recipient;
        $message->recipientName = 'Attendee';
        $message->significantChange = true;
        $message->message = Reader::read(self::EVENT);

        return $message;
    }

    private function plugin(MailerInterface $mailer, array &$logs): DavisIMipPlugin
    {
        self::bootKernel();

        $logger = new class($logs) extends AbstractLogger {
            public function __construct(private array &$logs)
            {
            }

            public function log($level, $message, array $context = []): void
            {
                $this->logs[] = $level.': '.$message;
            }
        };

        return new DavisIMipPlugin(
            $mailer,
            'no-reply@example.org',
            static::getContainer()->getParameter('kernel.project_dir').'/public',
            $logger
        );
    }

    private function mailer(?\Throwable $failure): MailerInterface
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

    public function testAnUnreachableTransportDoesNotAbortTheSchedulingEvent(): void
    {
        $logs = [];
        $plugin = $this->plugin($this->mailer(new TransportException('Connection refused')), $logs);

        $message = $this->message();
        $plugin->schedule($message);

        $this->assertStringStartsWith('5.1', (string) $message->scheduleStatus);
        $this->assertContains('error: iMIP: the invitation could not be sent', $logs);
    }

    public function testAnAddressThatIsNotAnEmailAddressDoesNotAbortTheSchedulingEvent(): void
    {
        $logs = [];
        $plugin = $this->plugin($this->mailer(null), $logs);

        $message = $this->message('mailto:attendee(at)example.org');
        $plugin->schedule($message);

        $this->assertStringStartsWith('5.3', (string) $message->scheduleStatus);
        $this->assertContains('error: iMIP: an address in the invitation is not a valid email address, no email sent', $logs);
    }

    public function testAnUnsupportedItipMethodIsReportedInsteadOfRenderingWithoutAnAction(): void
    {
        $logs = [];
        $plugin = $this->plugin($mailer = $this->mailer(null), $logs);

        $message = $this->message();
        $message->method = 'COUNTER';
        $plugin->schedule($message);

        $this->assertStringStartsWith('5.0', (string) $message->scheduleStatus);
        $this->assertContains('warning: iMIP: unsupported iTIP method, no email sent', $logs);
        $this->assertSame([], $mailer->sent);
    }

    public function testANonMailtoRecipientIsLogged(): void
    {
        $logs = [];
        $plugin = $this->plugin($mailer = $this->mailer(null), $logs);

        $message = $this->message('https://example.org/attendee');
        $plugin->schedule($message);

        $this->assertContains('warning: iMIP: not an email exchange, no invitation sent', $logs);
        $this->assertSame([], $mailer->sent);
    }

    public function testASuccessfulSendIsLoggedAndReported(): void
    {
        $logs = [];
        $plugin = $this->plugin($mailer = $this->mailer(null), $logs);

        $message = $this->message();
        $plugin->schedule($message);

        $this->assertStringStartsWith('1.1', (string) $message->scheduleStatus);
        $this->assertContains('info: iMIP: invitation sent', $logs);
        $this->assertCount(1, $mailer->sent);
    }
}
