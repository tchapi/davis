<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Sabre\CardDAV\Backend\PDO as CardDavBackend;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SyncTokenTest extends KernelTestCase
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

    /**
     * Builds an address book that has already seen ten changes: it now sits at sync token 11,
     * with a contact added at token 9 and another modified at token 10.
     */
    private function createAddressBookAtTokenEleven(): int
    {
        $pdo = $this->em->getConnection()->getNativeConnection();

        $id = $this->backend->createAddressBook(self::PRINCIPAL, 'sync-token-test', ['{DAV:}displayname' => 'Sync token test']);
        $pdo->prepare('UPDATE addressbooks SET synctoken = 11 WHERE id = ?')->execute([$id]);

        $insert = $pdo->prepare('INSERT INTO addressbookchanges (addressbookid, uri, synctoken, operation) VALUES (?, ?, ?, ?)');
        $insert->execute([$id, 'added.vcf', 9, 1]);
        $insert->execute([$id, 'changed.vcf', 10, 2]);

        return (int) $id;
    }

    /**
     * Regression test: sync tokens were stored as text, so `synctoken >= 9 AND synctoken < 11`
     * was compared lexicographically ('10' sorts before '9'). A client syncing across a
     * decimal-width boundary was told the collection had advanced but received no changes at
     * all, silently losing contacts.
     */
    public function testChangesAcrossADecimalBoundaryAreReported(): void
    {
        $addressBookId = $this->createAddressBookAtTokenEleven();

        $changes = $this->backend->getChangesForAddressBook($addressBookId, '9', 1);

        $this->assertSame(11, (int) $changes['syncToken']);
        $this->assertSame(['added.vcf'], $changes['added']);
        $this->assertSame(['changed.vcf'], $changes['modified']);
        $this->assertSame([], $changes['deleted']);
    }

    public function testChangesAreOrderedNumerically(): void
    {
        $addressBookId = $this->createAddressBookAtTokenEleven();

        // From token 10 the earlier change at 9 must be out of range, the one at 10 in range
        $changes = $this->backend->getChangesForAddressBook($addressBookId, '10', 1);

        $this->assertSame([], $changes['added']);
        $this->assertSame(['changed.vcf'], $changes['modified']);
    }
}
