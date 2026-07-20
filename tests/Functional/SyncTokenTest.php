<?php

namespace App\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Sabre\CardDAV\Backend\PDO as CardDavBackend;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SyncTokenTest extends KernelTestCase
{
    public function testAddressBookChangesUseNumericTokenOrdering(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $pdo = $entityManager->getConnection()->getNativeConnection();

        $statement = $pdo->prepare(<<<'SQL'
INSERT INTO addressbooks (principaluri, displayname, uri, description, synctoken)
VALUES (?, ?, ?, ?, ?)
SQL);
        $statement->execute(['principals/sync-token-test', 'Sync token test', 'sync-token-test', '', 11]);

        $statement = $pdo->prepare('SELECT id FROM addressbooks WHERE principaluri = ? AND uri = ?');
        $statement->execute(['principals/sync-token-test', 'sync-token-test']);
        $addressBookId = (int) $statement->fetchColumn();

        $statement = $pdo->prepare(<<<'SQL'
INSERT INTO addressbookchanges (addressbookid, uri, synctoken, operation)
VALUES (?, ?, ?, ?)
SQL);
        $statement->execute([$addressBookId, 'added.vcf', 9, 1]);
        $statement->execute([$addressBookId, 'changed.vcf', 10, 2]);

        $changes = (new CardDavBackend($pdo))->getChangesForAddressBook($addressBookId, '9', 1);

        self::assertSame(11, (int) $changes['syncToken']);
        self::assertSame(['added.vcf'], $changes['added']);
        self::assertSame(['changed.vcf'], $changes['modified']);
        self::assertSame([], $changes['deleted']);
    }
}
