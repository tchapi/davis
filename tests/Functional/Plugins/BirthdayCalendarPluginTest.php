<?php

declare(strict_types=1);

namespace App\Tests\Functional\Plugins;

use App\Entity\AddressBook;
use App\Entity\CalendarObject;
use App\Plugins\BirthdayCalendarPlugin;
use App\Services\BirthdayService;
use Doctrine\ORM\EntityManagerInterface;
use Sabre\CalDAV\Backend\PDO as CalendarBackend;
use Sabre\CardDAV;
use Sabre\DAV;
use Sabre\DAVACL\PrincipalBackend\PDO as PrincipalBackend;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * `BirthdayServiceTest` covers the service and `SyncBirthdayCalendarTest` the command. This covers
 * the piece between them: the plugin that turns a card written over CardDAV into a birthday event.
 */
class BirthdayCalendarPluginTest extends KernelTestCase
{
    private const PRINCIPAL = 'principals/test_user';
    private const CARD = "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Jane Doe\r\nUID:jane-1\r\nBDAY:19900615\r\nEND:VCARD\r\n";

    private EntityManagerInterface $em;
    private DAV\Server $server;
    private CardDAV\Backend\PDO $cardBackend;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $pdo = $this->em->getConnection()->getNativeConnection();

        $principalBackend = new PrincipalBackend($pdo);
        $this->cardBackend = new CardDAV\Backend\PDO($pdo);

        $this->server = new DAV\Server([
            new CardDAV\AddressBookRoot($principalBackend, $this->cardBackend),
        ]);
        $this->server->setBaseUri('/dav/');
        $this->server->addPlugin(new CardDAV\Plugin());
        $this->server->addPlugin(new BirthdayCalendarPlugin(
            $container->get(BirthdayService::class),
            new CalendarBackend($pdo)
        ));
    }

    private function addressBook(): AddressBook
    {
        return $this->em->getRepository(AddressBook::class)->findOneBy([
            'principalUri' => self::PRINCIPAL,
            'uri' => 'default',
        ]);
    }

    private function includeInBirthdayCalendar(bool $included): void
    {
        $this->addressBook()->setIncludedInBirthdayCalendar($included);
        $this->em->flush();
        $this->em->clear();
    }

    private function birthdayEvent(): ?CalendarObject
    {
        $this->em->clear();

        return $this->em->getRepository(CalendarObject::class)->findOneBy(['uri' => 'default-jane.vcf.ics']);
    }

    /**
     * Emits the event sabre emits after a `PUT` creates a card, which is what the plugin listens to.
     */
    private function writeCardThroughDav(): void
    {
        $path = 'addressbooks/test_user/default/jane.vcf';
        $this->cardBackend->createCard($this->addressBook()->getId(), 'jane.vcf', self::CARD);

        $this->server->emit('afterCreateFile', [$path, $this->server->tree->getNodeForPath(\dirname($path))]);
    }

    public function testWritingACardCreatesTheBirthdayEvent(): void
    {
        $this->includeInBirthdayCalendar(true);

        $this->writeCardThroughDav();

        $event = $this->birthdayEvent();
        $this->assertNotNull($event, 'The plugin should have produced a birthday event');
        $this->assertStringContainsString('Jane Doe', $event->getCalendarData());
    }

    public function testNothingHappensWhenTheAddressBookOptedOut(): void
    {
        $this->includeInBirthdayCalendar(false);

        $this->writeCardThroughDav();

        $this->assertNull($this->birthdayEvent());
    }

    public function testDeletingACardRemovesItsBirthdayEvent(): void
    {
        $this->includeInBirthdayCalendar(true);
        $this->writeCardThroughDav();
        $this->assertNotNull($this->birthdayEvent());

        $this->server->emit('beforeUnbind', ['addressbooks/test_user/default/jane.vcf']);

        $this->assertNull($this->birthdayEvent(), 'The birthday event should go with the card');
    }

    /**
     * The hooks fire for every write in the tree, not just cards.
     */
    public function testANonCardWriteIsIgnored(): void
    {
        $this->includeInBirthdayCalendar(true);

        $this->server->emit('afterCreateFile', [
            'addressbooks/test_user/default',
            $this->server->tree->getNodeForPath('addressbooks/test_user'),
        ]);

        $this->assertNull($this->birthdayEvent());
    }
}
