<?php

declare(strict_types=1);

namespace App\Tests\Functional\Plugins;

use App\Entity\Calendar;
use App\Entity\CalendarInstance;
use App\Entity\CalendarObject;
use App\Plugins\PublicAwareDAVACLPlugin;
use Doctrine\ORM\EntityManagerInterface;
use Sabre\CalDAV;
use Sabre\CalDAV\Backend\PDO as CalendarBackend;
use Sabre\DAV;
use Sabre\DAVACL\PrincipalBackend\PDO as PrincipalBackend;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The plugin grants `{DAV:}read` to everyone on a calendar marked public. It answers that per node,
 * and remembers the answer for the rest of the request, so these check it gives the right answer
 * for each calendar rather than reusing the first one.
 */
class PublicAwareDAVACLPluginTest extends KernelTestCase
{
    private const PRINCIPAL = 'principals/test_user';

    private EntityManagerInterface $em;
    private DAV\Server $server;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $pdo = $this->em->getConnection()->getNativeConnection();

        $this->server = new DAV\Server([
            new CalDAV\CalendarRoot(new PrincipalBackend($pdo), new CalendarBackend($pdo)),
        ]);
        $this->server->setBaseUri('/dav/');
        $this->server->addPlugin(new CalDAV\Plugin());
    }

    private function plugin(): PublicAwareDAVACLPlugin
    {
        return new PublicAwareDAVACLPlugin($this->em, true);
    }

    /**
     * Adds a calendar with one event in it and returns its uri.
     */
    private function makeCalendar(string $uri, bool $public): string
    {
        $calendar = new Calendar();
        $instance = (new CalendarInstance())->setCalendar($calendar)
            ->setPrincipalUri(self::PRINCIPAL)->setUri($uri)->setDisplayName($uri)->setPublic($public);
        $this->em->persist($calendar);
        $this->em->persist($instance);
        $this->em->persist((new CalendarObject())->setCalendar($calendar)->setUri($uri.'-event.ics')
            ->setCalendarData("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:$uri\r\nDTSTART:20260101T120000Z\r\nSUMMARY:$uri\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n")
            ->setComponentType(Calendar::COMPONENT_EVENTS)->setSize(120)->setEtag('"e"')->setLastModified(time()));
        $this->em->flush();

        return $uri;
    }

    private function grantsReadToEveryone(array $acl): bool
    {
        foreach ($acl as $ace) {
            if ('{DAV:}all' === $ace['principal'] && '{DAV:}read' === $ace['privilege']) {
                return true;
            }
        }

        return false;
    }

    private function aclFor(PublicAwareDAVACLPlugin $plugin, string $path): array
    {
        return $plugin->getAcl($this->server->tree->getNodeForPath($path));
    }

    public function testOnlyThePublicCalendarIsReadableByEveryone(): void
    {
        $this->makeCalendar('shared_one', true);
        $this->makeCalendar('private_one', false);

        $plugin = $this->plugin();

        $this->assertTrue($this->grantsReadToEveryone($this->aclFor($plugin, 'calendars/test_user/shared_one')));
        $this->assertFalse($this->grantsReadToEveryone($this->aclFor($plugin, 'calendars/test_user/private_one')));
    }

    /**
     * The order matters: asking about the public one first must not make the private one public.
     */
    public function testTheAnswerIsNotReusedAcrossCalendars(): void
    {
        $this->makeCalendar('public_first', true);
        $this->makeCalendar('private_second', false);

        $plugin = $this->plugin();
        $this->aclFor($plugin, 'calendars/test_user/public_first');
        $this->assertFalse($this->grantsReadToEveryone($this->aclFor($plugin, 'calendars/test_user/private_second')));

        // and the other way round
        $plugin = $this->plugin();
        $this->aclFor($plugin, 'calendars/test_user/private_second');
        $this->assertTrue($this->grantsReadToEveryone($this->aclFor($plugin, 'calendars/test_user/public_first')));
    }

    /**
     * The objects inside a public calendar have to be readable too, not just the collection.
     */
    public function testTheObjectsOfAPublicCalendarAreReadableByEveryone(): void
    {
        $this->makeCalendar('with_event', true);

        $plugin = $this->plugin();

        $this->assertTrue($this->grantsReadToEveryone($this->aclFor($plugin, 'calendars/test_user/with_event/with_event-event.ics')));
    }

    public function testNothingIsPublicWhenTheFeatureIsOff(): void
    {
        $this->makeCalendar('off_but_public', true);

        $plugin = new PublicAwareDAVACLPlugin($this->em, false);

        $this->assertFalse($this->grantsReadToEveryone($this->aclFor($plugin, 'calendars/test_user/off_but_public')));
    }
}
