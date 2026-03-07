<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Constants;
use App\Entity\AddressBook;
use App\Entity\CalendarInstance;
use App\Entity\CalendarObject;
use App\Entity\Card;
use App\Entity\Principal;
use App\Entity\User;
use App\Services\BirthdayService;
use Doctrine\ORM\EntityManagerInterface;
use Sabre\CalDAV\Backend\PDO as CalendarBackend;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class SyncBirthdayCalendarTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $birthdayService = static::getContainer()->get(BirthdayService::class);
        $pdo = $this->em->getConnection()->getNativeConnection();
        $birthdayService->setBackend(new CalendarBackend($pdo));

        $application = new Application(self::$kernel);
        $command = $application->find('dav:sync-birthday-calendar');
        $this->commandTester = new CommandTester($command);

        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    private function createUser(string $username): User
    {
        $user = (new User())
            ->setUsername($username)
            ->setPassword('hashed');
        $this->em->persist($user);

        $principal = (new Principal())
            ->setUri(Principal::PREFIX.$username)
            ->setEmail($username.'@example.com')
            ->setDisplayName($username);
        $this->em->persist($principal);

        $this->em->flush();

        return $user;
    }

    private function createAddressBookWithCard(string $username, string $cardUri, string $cardData): AddressBook
    {
        $addressBook = (new AddressBook())
            ->setPrincipalUri(Principal::PREFIX.$username)
            ->setUri('default')
            ->setDisplayName('Default')
            ->setDescription('')
            ->setSynctoken('1')
            ->setIncludedInBirthdayCalendar(true);
        $this->em->persist($addressBook);

        $card = (new Card())
            ->setAddressBook($addressBook)
            ->setUri($cardUri)
            ->setCarddata($cardData)
            ->setLastmodified(time())
            ->setSize(strlen($cardData))
            ->setEtag(md5($cardData));
        $this->em->persist($card);

        $this->em->flush();

        return $addressBook;
    }

    private function assertBirthdayEventExists(string $principalUri, string $addressBookUri, string $cardUri, string $expectedNameFragment): void
    {
        $instance = $this->em->getRepository(CalendarInstance::class)->findOneBy([
            'principalUri' => $principalUri,
            'uri' => Constants::BIRTHDAY_CALENDAR_URI,
        ]);
        $this->assertNotNull($instance, "Birthday calendar instance not found for $principalUri");

        $objectUri = $addressBookUri.'-'.$cardUri.'.ics';
        $object = $this->em->getRepository(CalendarObject::class)->findOneBy([
            'calendar' => $instance->getCalendar(),
            'uri' => $objectUri,
        ]);
        $this->assertNotNull($object, "Calendar object $objectUri not found");
        $this->assertStringContainsString($expectedNameFragment, $object->getCalendarData());
    }

    public function testExecuteSyncsAllUsers(): void
    {
        $this->createUser('alice');
        $this->createUser('bob');
        $this->createAddressBookWithCard(
            'alice',
            'alice-contact.vcf',
            "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Alice Contact\r\nUID:alice-1\r\nBDAY:19900615\r\nEND:VCARD\r\n"
        );
        $this->createAddressBookWithCard(
            'bob',
            'bob-contact.vcf',
            "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Bob Contact\r\nUID:bob-1\r\nBDAY:19850320\r\nEND:VCARD\r\n"
        );

        $this->commandTester->execute([]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Start birthday calendar sync for all users', $this->commandTester->getDisplay());

        $this->em->clear();

        $this->assertBirthdayEventExists(Principal::PREFIX.'alice', 'default', 'alice-contact.vcf', 'Alice Contact');
        $this->assertBirthdayEventExists(Principal::PREFIX.'bob', 'default', 'bob-contact.vcf', 'Bob Contact');
    }

    public function testExecuteSyncsSingleUser(): void
    {
        $this->createUser('alice');
        $this->createUser('bob');
        $this->createAddressBookWithCard(
            'alice',
            'alice-contact.vcf',
            "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Alice Contact\r\nUID:alice-1\r\nBDAY:19900615\r\nEND:VCARD\r\n"
        );
        $this->createAddressBookWithCard(
            'bob',
            'bob-contact.vcf',
            "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Bob Contact\r\nUID:bob-1\r\nBDAY:19850320\r\nEND:VCARD\r\n"
        );

        $this->commandTester->execute(['username' => 'alice']);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Start birthday calendar sync for alice', $this->commandTester->getDisplay());

        $this->em->clear();

        // Alice's birthday calendar should exist with the event
        $this->assertBirthdayEventExists(Principal::PREFIX.'alice', 'default', 'alice-contact.vcf', 'Alice Contact');

        // Bob's birthday calendar should NOT have been created
        $bobInstance = $this->em->getRepository(CalendarInstance::class)->findOneBy([
            'principalUri' => Principal::PREFIX.'bob',
            'uri' => Constants::BIRTHDAY_CALENDAR_URI,
        ]);
        $this->assertNull($bobInstance);
    }

    public function testExecuteThrowsExceptionForUnknownUser(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User <unknown> is unknown.');

        $this->commandTester->execute(['username' => 'unknown']);
    }

    public function testExecuteWithNoUsersInDatabaseSucceeds(): void
    {
        $this->commandTester->execute([]);

        $this->assertSame(0, $this->commandTester->getStatusCode());

        $instances = $this->em->getRepository(CalendarInstance::class)->findBy(['uri' => Constants::BIRTHDAY_CALENDAR_URI]);
        $this->assertCount(0, $instances);
    }
}
