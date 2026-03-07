<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Constants;
use App\Entity\AddressBook;
use App\Entity\CalendarInstance;
use App\Entity\CalendarObject;
use App\Entity\Principal;
use App\Services\BirthdayService;
use Doctrine\ORM\EntityManagerInterface;
use Sabre\CalDAV\Backend\PDO as CalendarBackend;
use Sabre\VObject\Component\VCalendar;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class BirthdayServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private BirthdayService $service;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->service = static::getContainer()->get(BirthdayService::class);

        $pdo = $this->em->getConnection()->getNativeConnection();
        $this->service->setBackend(new CalendarBackend($pdo));

        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    private function createAddressBook(
        string $username = 'testuser',
        bool $includedInBirthdayCalendar = true,
    ): AddressBook {
        $principal = (new Principal())
            ->setUri(Principal::PREFIX.$username)
            ->setEmail($username.'@example.com')
            ->setDisplayName($username);
        $this->em->persist($principal);

        $addressBook = (new AddressBook())
            ->setPrincipalUri(Principal::PREFIX.$username)
            ->setUri('default')
            ->setDisplayName('Default')
            ->setDescription('')
            ->setSynctoken('1')
            ->setIncludedInBirthdayCalendar($includedInBirthdayCalendar);
        $this->em->persist($addressBook);
        $this->em->flush();

        return $addressBook;
    }

    // -------------------------------------------------------------------------
    // buildDataFromContact
    // -------------------------------------------------------------------------

    public function testBuildDataFromContactReturnsNullForEmptyData(): void
    {
        $this->assertNull($this->service->buildDataFromContact(''));
    }

    public function testBuildDataFromContactReturnsNullIfNoBday(): void
    {
        $vcard = "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:John Doe\r\nEND:VCARD\r\n";
        $this->assertNull($this->service->buildDataFromContact($vcard));
    }

    public function testBuildDataFromContactReturnsNullIfNoFn(): void
    {
        $vcard = "BEGIN:VCARD\r\nVERSION:3.0\r\nBDAY:19900101\r\nEND:VCARD\r\n";
        $this->assertNull($this->service->buildDataFromContact($vcard));
    }

    public function testBuildDataFromContactReturnsVCalendarWithBday(): void
    {
        $vcard = "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:John Doe\r\nUID:1234\r\nBDAY:19900615\r\nEND:VCARD\r\n";
        $result = $this->service->buildDataFromContact($vcard);

        $this->assertInstanceOf(VCalendar::class, $result);
        $this->assertStringContainsString('John Doe', (string) $result->VEVENT->SUMMARY);
        $this->assertStringContainsString('1990', (string) $result->VEVENT->SUMMARY);
        $this->assertEquals('FREQ=YEARLY', (string) $result->VEVENT->RRULE);
        $this->assertEquals('DATE', (string) $result->VEVENT->DTSTART['VALUE']);
    }

    public function testBuildDataFromContactHandlesLeapDay(): void
    {
        $vcard = "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:John Doe\r\nUID:1234\r\nBDAY:19920229\r\nEND:VCARD\r\n";
        $result = $this->service->buildDataFromContact($vcard);

        $this->assertInstanceOf(VCalendar::class, $result);
        $this->assertStringContainsString('BYMONTH=2;BYMONTHDAY=-1', (string) $result->VEVENT->RRULE);
    }

    public function testBuildDataFromContactHandlesOmitYear(): void
    {
        $vcard = "BEGIN:VCARD\r\nVERSION:4.0\r\nFN:John Doe\r\nUID:1234\r\nBDAY;X-APPLE-OMIT-YEAR=1604:16040615\r\nEND:VCARD\r\n";
        $result = $this->service->buildDataFromContact($vcard);

        $this->assertInstanceOf(VCalendar::class, $result);
        $this->assertStringNotContainsString('(', (string) $result->VEVENT->SUMMARY);
    }

    public function testBuildDataFromContactAddsAlarm(): void
    {
        $vcard = "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:John Doe\r\nUID:1234\r\nBDAY:19900615\r\nEND:VCARD\r\n";
        $result = $this->service->buildDataFromContact($vcard);

        $this->assertNotNull($result->VEVENT->VALARM);
    }

    // -------------------------------------------------------------------------
    // birthdayEventChanged
    // -------------------------------------------------------------------------

    public function testBirthdayEventChangedReturnsFalseWhenSame(): void
    {
        $cal = $this->service->buildDataFromContact("BEGIN:VCARD\r\nVERSION:3.0\r\nFN:John Doe\r\nUID:1234\r\nBDAY:19900615\r\nEND:VCARD\r\n");

        $this->assertFalse($this->service->birthdayEventChanged($cal->serialize(), $cal));
    }

    public function testBirthdayEventChangedReturnsTrueWhenDifferentDate(): void
    {
        $cal1 = $this->service->buildDataFromContact("BEGIN:VCARD\r\nVERSION:3.0\r\nFN:John Doe\r\nUID:1234\r\nBDAY:19900615\r\nEND:VCARD\r\n");
        $cal2 = $this->service->buildDataFromContact("BEGIN:VCARD\r\nVERSION:3.0\r\nFN:John Doe\r\nUID:1234\r\nBDAY:19900616\r\nEND:VCARD\r\n");

        $this->assertTrue($this->service->birthdayEventChanged($cal1->serialize(), $cal2));
    }

    public function testBirthdayEventChangedReturnsTrueWhenDifferentName(): void
    {
        $cal1 = $this->service->buildDataFromContact("BEGIN:VCARD\r\nVERSION:3.0\r\nFN:John Doe\r\nUID:1234\r\nBDAY:19900615\r\nEND:VCARD\r\n");
        $cal2 = $this->service->buildDataFromContact("BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Jane Doe\r\nUID:1234\r\nBDAY:19900615\r\nEND:VCARD\r\n");

        $this->assertTrue($this->service->birthdayEventChanged($cal1->serialize(), $cal2));
    }

    public function testBirthdayEventChangedReturnsTrueOnInvalidExistingData(): void
    {
        $cal = $this->service->buildDataFromContact("BEGIN:VCARD\r\nVERSION:3.0\r\nFN:John Doe\r\nUID:1234\r\nBDAY:19900615\r\nEND:VCARD\r\n");

        $this->assertTrue($this->service->birthdayEventChanged('invalid-data', $cal));
    }

    // -------------------------------------------------------------------------
    // onCardChanged
    // -------------------------------------------------------------------------

    public function testOnCardChangedSkipsIfNotIncludedInBirthdayCalendar(): void
    {
        $addressBook = $this->createAddressBook(includedInBirthdayCalendar: false);

        $this->service->onCardChanged(
            $addressBook->getId(),
            'john.vcf',
            "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:John Doe\r\nUID:1234\r\nBDAY:19900615\r\nEND:VCARD\r\n"
        );

        $this->em->clear();

        $object = $this->em->getRepository(CalendarObject::class)->findOneBy(['uri' => 'default-john.vcf.ics']);
        $this->assertNull($object);
    }

    public function testOnCardChangedCreatesCalendarObject(): void
    {
        $addressBook = $this->createAddressBook();

        $this->service->onCardChanged(
            $addressBook->getId(),
            'john.vcf',
            "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:John Doe\r\nUID:1234\r\nBDAY:19900615\r\nEND:VCARD\r\n"
        );

        $this->em->clear();

        $object = $this->em->getRepository(CalendarObject::class)->findOneBy(['uri' => 'default-john.vcf.ics']);
        $this->assertNotNull($object);
        $this->assertStringContainsString('John Doe', $object->getCalendarData());
    }

    public function testOnCardChangedUpdatesExistingCalendarObject(): void
    {
        $addressBook = $this->createAddressBook();

        $this->service->onCardChanged(
            $addressBook->getId(),
            'john.vcf',
            "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:John Doe\r\nUID:1234\r\nBDAY:19900615\r\nEND:VCARD\r\n"
        );

        $this->service->onCardChanged(
            $addressBook->getId(),
            'john.vcf',
            "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:John Updated\r\nUID:1234\r\nBDAY:19900615\r\nEND:VCARD\r\n"
        );

        $this->em->clear();

        $object = $this->em->getRepository(CalendarObject::class)->findOneBy(['uri' => 'default-john.vcf.ics']);
        $this->assertNotNull($object);
        $this->assertStringContainsString('John Updated', $object->getCalendarData());

        $this->em->clear();

        $instanceBefore = $this->em->getRepository(CalendarInstance::class)->findOneBy([
            'principalUri' => 'principals/testuser',
            'uri' => Constants::BIRTHDAY_CALENDAR_URI,
        ]);
        $syncTokenBefore = $instanceBefore->getCalendar()->getSynctoken();

        $this->service->onCardChanged(
            $addressBook->getId(),
            'john.vcf',
            "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:John Updated Again\r\nUID:1234\r\nBDAY:19900615\r\nEND:VCARD\r\n"
        );

        $this->em->clear();

        $instanceAfter = $this->em->getRepository(CalendarInstance::class)->findOneBy([
            'principalUri' => 'principals/testuser',
            'uri' => Constants::BIRTHDAY_CALENDAR_URI,
        ]);
        $this->assertGreaterThan($syncTokenBefore, $instanceAfter->getCalendar()->getSynctoken());
    }

    public function testOnCardChangedDoesNotCreateObjectIfNoBday(): void
    {
        $addressBook = $this->createAddressBook();

        $this->service->onCardChanged(
            $addressBook->getId(),
            'john.vcf',
            "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:John Doe\r\nUID:1234\r\nEND:VCARD\r\n"
        );

        $this->em->clear();

        $object = $this->em->getRepository(CalendarObject::class)->findOneBy(['uri' => 'default-john.vcf.ics']);
        $this->assertNull($object);
    }

    // -------------------------------------------------------------------------
    // onCardDeleted
    // -------------------------------------------------------------------------

    public function testOnCardDeletedRemovesCalendarObject(): void
    {
        $addressBook = $this->createAddressBook();

        $this->service->onCardChanged(
            $addressBook->getId(),
            'john.vcf',
            "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:John Doe\r\nUID:1234\r\nBDAY:19900615\r\nEND:VCARD\r\n"
        );

        $this->service->onCardDeleted($addressBook->getId(), 'john.vcf');

        $this->em->clear();

        $object = $this->em->getRepository(CalendarObject::class)->findOneBy(['uri' => 'default-john.vcf.ics']);
        $this->assertNull($object);
    }

    public function testOnCardDeletedIsNoopIfNoCalendarObject(): void
    {
        $addressBook = $this->createAddressBook();

        // Should not throw even if no calendar object exists
        $this->service->onCardDeleted($addressBook->getId(), 'nonexistent.vcf');

        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // ensureBirthdayCalendarExists
    // -------------------------------------------------------------------------

    public function testEnsureBirthdayCalendarExistsCreatesCalendar(): void
    {
        $this->service->ensureBirthdayCalendarExists('principals/testuser');

        $instance = $this->em->getRepository(CalendarInstance::class)->findOneBy([
            'principalUri' => 'principals/testuser',
            'uri' => Constants::BIRTHDAY_CALENDAR_URI,
        ]);

        $this->assertNotNull($instance);
        $this->assertNotNull($instance->getCalendar());
    }

    public function testEnsureBirthdayCalendarExistsIsIdempotent(): void
    {
        $this->service->ensureBirthdayCalendarExists('principals/testuser');
        $this->service->ensureBirthdayCalendarExists('principals/testuser');

        $instances = $this->em->getRepository(CalendarInstance::class)->findBy([
            'principalUri' => 'principals/testuser',
            'uri' => Constants::BIRTHDAY_CALENDAR_URI,
        ]);

        $this->assertCount(1, $instances);
    }

    // -------------------------------------------------------------------------
    // syncPrincipal
    // -------------------------------------------------------------------------

    public function testSyncPrincipalDeletesBirthdayCalendarIfNoAddressBooksIncluded(): void
    {
        $this->service->ensureBirthdayCalendarExists('principals/testuser');
        $this->createAddressBook(includedInBirthdayCalendar: false);

        $this->service->syncPrincipal('principals/testuser');

        $instance = $this->em->getRepository(CalendarInstance::class)->findOneBy([
            'principalUri' => 'principals/testuser',
            'uri' => Constants::BIRTHDAY_CALENDAR_URI,
        ]);
        $this->assertNull($instance);
    }
}
