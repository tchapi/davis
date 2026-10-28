<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Plugins\DefaultAvailabilityPlugin;
use Doctrine\ORM\EntityManagerInterface;
use Sabre\CalDAV\Backend\PDO as CalendarBackend;
use Sabre\CalDAV\Schedule\Inbox;
use Sabre\CalDAV\Schedule\Plugin as SchedulePlugin;
use Sabre\DAV\PropertyStorage\Backend\PDO as PropertyStorageBackend;
use Sabre\DAV\PropertyStorage\Plugin as PropertyStoragePlugin;
use Sabre\DAV\Server;
use Sabre\HTTP\Request;
use Sabre\HTTP\Sapi;
use Sabre\VObject\Reader;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Regression test for issue #317: macOS Calendar errors out when the inbox has no
 * calendar-availability property, which is the case for every user until a client
 * writes one.
 */
class DefaultAvailabilityTest extends KernelTestCase
{
    private const PRINCIPAL = 'principals/test_user';
    public const INBOX = 'inbox';
    private const CALDAV_PROPERTY = DefaultAvailabilityPlugin::AVAILABILITY_PROPERTY;
    private const CALENDARSERVER_PROPERTY = '{http://calendarserver.org/ns/}calendar-availability';

    private EntityManagerInterface $em;
    private Server $server;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $pdo = $this->em->getConnection()->getNativeConnection();

        $this->em->getConnection()->beginTransaction();

        // sabre builds its request from $_SERVER, which has no REQUEST_METHOD under phpunit
        $sapi = new class extends Sapi {
            public static function getRequest(): Request
            {
                return new Request('PROPFIND', '/'.DefaultAvailabilityTest::INBOX.'/');
            }
        };

        // Same plugin stack as DAVController, reduced to what the property needs
        $this->server = new Server([new Inbox(new CalendarBackend($pdo), self::PRINCIPAL)], $sapi);
        $this->server->addPlugin(new PropertyStoragePlugin(new PropertyStorageBackend($pdo)));
        $this->server->addPlugin(new SchedulePlugin());
        $this->server->addPlugin(new DefaultAvailabilityPlugin());
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    private function propfind(string $property, string $method = 'PROPFIND'): array
    {
        $this->server->httpRequest = new Request($method, '/'.self::INBOX.'/');
        $result = $this->server->getPropertiesForPath(self::INBOX, [$property], 0);

        return $result[0];
    }

    public function testAnInboxWithoutAvailabilityGetsAValidDefault(): void
    {
        $result = $this->propfind(self::CALDAV_PROPERTY);

        $this->assertArrayHasKey(self::CALDAV_PROPERTY, $result[200]);

        $vcalendar = Reader::read($result[200][self::CALDAV_PROPERTY]);
        $this->assertSame([], $vcalendar->validate(), 'The default availability must be valid iCalendar');
        $this->assertNotNull($vcalendar->VAVAILABILITY);

        $available = $vcalendar->VAVAILABILITY->AVAILABLE;
        $this->assertNotNull($available);

        // macOS Calendar only displays a weekly rule whose slot ends on the day it starts
        $this->assertSame('WEEKLY', $available->RRULE->getParts()['FREQ']);
        $this->assertSame(
            $available->DTSTART->getDateTime()->format('Ymd'),
            $available->DTEND->getDateTime()->format('Ymd')
        );

        // Floating, so that every client reads it in its own time zone
        $this->assertTrue($available->DTSTART->isFloating());
        $this->assertTrue($available->DTEND->isFloating());
    }

    /**
     * macOS Calendar asks for the pre-RFC 7953 name, which sabre maps onto the CalDAV one.
     */
    public function testTheDefaultIsAlsoServedUnderTheCalendarServerName(): void
    {
        $result = $this->propfind(self::CALENDARSERVER_PROPERTY);

        $this->assertSame(DefaultAvailabilityPlugin::DEFAULT_AVAILABILITY, $result[200][self::CALENDARSERVER_PROPERTY]);
    }

    public function testAStoredAvailabilityWins(): void
    {
        $stored = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:test\r\nBEGIN:VAVAILABILITY\r\nUID:stored\r\nDTSTAMP:20260101T000000Z\r\nEND:VAVAILABILITY\r\nEND:VCALENDAR\r\n";
        $patch = $this->server->updateProperties(self::INBOX, [self::CALDAV_PROPERTY => $stored]);
        $this->assertSame(200, $patch[self::CALDAV_PROPERTY]);

        $result = $this->propfind(self::CALDAV_PROPERTY);

        $this->assertSame($stored, $result[200][self::CALDAV_PROPERTY]);
    }

    /**
     * Free-busy generation reads the property while answering a POST on the outbox: it must
     * keep seeing nothing, so that users without a stored availability stay always available.
     */
    public function testTheDefaultIsNotServedOutsidePropfind(): void
    {
        $result = $this->propfind(self::CALDAV_PROPERTY, 'POST');

        $this->assertArrayNotHasKey(self::CALDAV_PROPERTY, $result[200]);
        $this->assertArrayHasKey(self::CALDAV_PROPERTY, $result[404]);
    }
}
