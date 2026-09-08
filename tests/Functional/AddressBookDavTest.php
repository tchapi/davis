<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Sabre\CardDAV\Backend\PDO as CardDavBackend;
use Sabre\DAV\PropPatch;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AddressBookDavTest extends KernelTestCase
{
    private const PRINCIPAL = 'principals/test_user';

    private EntityManagerInterface $em;
    private CardDavBackend $backend;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->backend = new CardDavBackend($this->em->getConnection()->getNativeConnection());

        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    private function addressBookFor(string $uri): array
    {
        foreach ($this->backend->getAddressBooksForUser(self::PRINCIPAL) as $book) {
            if ($uri === $book['uri']) {
                return $book;
            }
        }

        $this->fail(sprintf('No address book found for uri "%s"', $uri));
    }

    /**
     * Regression test for issue #275: a display name is optional in CardDAV, but the column
     * was NOT NULL, so an MKCOL without {DAV:}displayname failed with a 500.
     */
    public function testAddressBookCanBeCreatedWithoutADisplayName(): void
    {
        $this->backend->createAddressBook(self::PRINCIPAL, 'nameless', []);

        $this->assertNull($this->addressBookFor('nameless')['{DAV:}displayname']);
    }

    public function testADisplayNameIsStillStoredWhenGiven(): void
    {
        $this->backend->createAddressBook(self::PRINCIPAL, 'named', ['{DAV:}displayname' => 'My contacts']);

        $this->assertSame('My contacts', $this->addressBookFor('named')['{DAV:}displayname']);
    }

    /**
     * Same column, the other way round: a client may remove the display name with a PROPPATCH.
     */
    public function testADisplayNameCanBeRemovedAgain(): void
    {
        $id = $this->backend->createAddressBook(self::PRINCIPAL, 'transient', ['{DAV:}displayname' => 'Temporary']);

        $propPatch = new PropPatch(['{DAV:}displayname' => null]);
        $this->backend->updateAddressBook($id, $propPatch);

        $this->assertTrue($propPatch->commit(), 'The PROPPATCH should succeed');
        $this->assertNull($this->addressBookFor('transient')['{DAV:}displayname']);
    }
}
