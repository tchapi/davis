<?php

declare(strict_types=1);

namespace App\Tests\Functional\Plugins;

use App\Plugins\DavisIMipPlugin;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\AbstractLogger;
use Sabre\CalDAV;
use Sabre\CalDAV\Backend\PDO as CalendarBackend;
use Sabre\DAV;
use Sabre\DAVACL\PrincipalBackend\PDO as PrincipalBackend;
use Sabre\HTTP\Request;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * Covers the logging the iMIP plugin does when an event has attendees but sabre sends no
 * invitation. In that case sabre never emits `schedule`, so none of the plugin's other logging
 * runs and the log would otherwise be empty.
 */
class ImipSchedulingDiagnosticsTest extends KernelTestCase
{
    private const PRINCIPAL = 'principals/test_user';
    private const CALENDAR_PATH = 'calendars/test_user/default';

    private EntityManagerInterface $em;
    private DAV\Server $server;
    private CalendarBackend $calendarBackend;

    /** @var object{records: array<int, array{level: string, message: string, context: array}>} */
    private $logger;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $pdo = $this->em->getConnection()->getNativeConnection();

        $principalBackend = new PrincipalBackend($pdo);
        $this->calendarBackend = new CalendarBackend($pdo);

        $this->logger = new class extends AbstractLogger {
            public array $records = [];

            public function log($level, $message, array $context = []): void
            {
                $this->records[] = ['level' => (string) $level, 'message' => (string) $message, 'context' => $context];
            }
        };

        $mailer = new class implements MailerInterface {
            public function send(RawMessage $message, ?\Symfony\Component\Mailer\Envelope $envelope = null): void
            {
            }
        };

        $this->server = new DAV\Server([
            new CalDAV\Principal\Collection($principalBackend),
            new CalDAV\CalendarRoot($principalBackend, $this->calendarBackend),
        ]);
        $this->server->setBaseUri('/dav/');
        $this->server->httpRequest = new Request('PUT', '/dav/'.self::CALENDAR_PATH.'/probe.ics');

        // The CalDAV plugin is what answers `calendar-user-address-set` on a principal
        $this->server->addPlugin(new CalDAV\Plugin());
        $this->server->addPlugin(new DavisIMipPlugin($mailer, 'davis@test.com', '', $this->logger));
    }

    /**
     * sabre hands back the id as a [calendar, instance] pair, and wants the same pair back.
     */
    private function calendarId()
    {
        foreach ($this->calendarBackend->getCalendarsForUser(self::PRINCIPAL) as $calendar) {
            if ('default' === $calendar['uri']) {
                return $calendar['id'];
            }
        }

        $this->fail('The fixtures should provide a default calendar for test_user');
    }

    /**
     * Saves the event like a PUT would, then fires the event sabre fires after a write.
     */
    private function writeEvent(string $organizer, string $attendeeParams = '', array $headers = []): void
    {
        $uri = 'probe-'.uniqid().'.ics';
        $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:probe-1\r\n"
            ."DTSTART:20260101T120000Z\r\nSUMMARY:Probe\r\n"
            .('' === $organizer ? '' : 'ORGANIZER:'.$organizer."\r\n")
            .'ATTENDEE'.$attendeeParams.":mailto:someone@elsewhere.test\r\n"
            ."END:VEVENT\r\nEND:VCALENDAR\r\n";

        $this->calendarBackend->createCalendarObject($this->calendarId(), $uri, $ics);

        $path = self::CALENDAR_PATH.'/'.$uri;
        $request = new Request('PUT', '/dav/'.$path);
        foreach ($headers as $name => $value) {
            $request->setHeader($name, $value);
        }
        $this->server->httpRequest = $request;

        $this->server->emit('afterCreateFile', [$path, $this->server->tree->getNodeForPath(\dirname($path))]);
    }

    /**
     * @return array<int, array{level: string, message: string, context: array}>
     */
    private function warnings(): array
    {
        return array_values(array_filter($this->logger->records, fn (array $r) => 'warning' === $r['level']));
    }

    public function testAnOrganiserThatIsNotTheAccountsAddressIsExplained(): void
    {
        $this->writeEvent('mailto:someone-else@elsewhere.test');

        $warnings = $this->warnings();
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('not one of this account', $warnings[0]['message']);

        // Both addresses have to be in the log, otherwise you cannot tell what to change
        $this->assertSame('mailto:someone-else@elsewhere.test', $warnings[0]['context']['organizer']);
        $this->assertContains('mailto:test@test.com', $warnings[0]['context']['account_addresses']);
    }

    public function testAMatchingOrganiserSaysNothing(): void
    {
        $this->writeEvent('mailto:test@test.com');

        $this->assertSame([], $this->warnings());
    }

    public function testAnEventWithoutAttendeesSaysNothing(): void
    {
        $uri = 'no-attendees.ics';
        $this->calendarBackend->createCalendarObject($this->calendarId(), $uri,
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:solo\r\nDTSTART:20260101T120000Z\r\nSUMMARY:Solo\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");

        $path = self::CALENDAR_PATH.'/'.$uri;
        $this->server->emit('afterCreateFile', [$path, $this->server->tree->getNodeForPath(\dirname($path))]);

        $this->assertSame([], $this->warnings());
    }

    public function testAClientOptingOutIsExplained(): void
    {
        $this->writeEvent('mailto:test@test.com', '', ['Schedule-Reply' => 'F']);

        $warnings = $this->warnings();
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('Schedule-Reply: F', $warnings[0]['message']);
    }

    public function testAnAttendeeHandlingItsOwnSchedulingIsExplained(): void
    {
        $this->writeEvent('mailto:test@test.com', ';SCHEDULE-AGENT=CLIENT');

        $warnings = $this->warnings();
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('SCHEDULE-AGENT=CLIENT', $warnings[0]['message']);
    }

    /**
     * The check runs after the write, so an error inside it must stay hidden.
     */
    public function testAPathThatNoLongerResolvesIsNotFatal(): void
    {
        $this->server->emit('afterCreateFile', [self::CALENDAR_PATH.'/gone.ics', null]);

        $this->assertSame([], $this->warnings());
    }
}
