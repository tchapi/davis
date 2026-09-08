<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Sabre\CalDAV\Backend\PDO as CalendarBackend;
use Sabre\DAV\Xml\Property\Href;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CalendarSubscriptionTest extends KernelTestCase
{
    private const PRINCIPAL = 'principals/test_user';
    private const SOURCE = 'https://example.org/holidays.ics';

    private EntityManagerInterface $em;
    private CalendarBackend $backend;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->backend = new CalendarBackend($this->em->getConnection()->getNativeConnection());

        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    private function subscriptionFor(string $uri): array
    {
        foreach ($this->backend->getSubscriptionsForUser(self::PRINCIPAL) as $subscription) {
            if ($uri === $subscription['uri']) {
                return $subscription;
            }
        }

        $this->fail(sprintf('No subscription found for uri "%s"', $uri));
    }

    /**
     * Regression test: `calendarorder` had no default, and sabre only lists it in its INSERT
     * when the client sent {http://apple.com/ns/ical/}calendar-order. Subscribing without one
     * therefore failed with a NOT NULL violation.
     */
    public function testSubscriptionCanBeCreatedWithoutACalendarOrder(): void
    {
        $this->backend->createSubscription(self::PRINCIPAL, 'holidays', [
            '{http://calendarserver.org/ns/}source' => new Href(self::SOURCE),
        ]);

        $subscription = $this->subscriptionFor('holidays');

        $this->assertSame(self::SOURCE, $subscription['source']);
        $this->assertSame(0, (int) $subscription['{http://apple.com/ns/ical/}calendar-order']);
    }

    public function testAnExplicitCalendarOrderIsStillHonoured(): void
    {
        $this->backend->createSubscription(self::PRINCIPAL, 'ordered', [
            '{http://calendarserver.org/ns/}source' => new Href(self::SOURCE),
            '{http://apple.com/ns/ical/}calendar-order' => 3,
        ]);

        $this->assertSame(3, (int) $this->subscriptionFor('ordered')['{http://apple.com/ns/ical/}calendar-order']);
    }
}
