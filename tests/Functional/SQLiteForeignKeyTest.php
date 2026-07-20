<?php

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SQLiteForeignKeyTest extends KernelTestCase
{
    public function testForeignKeysAreEnforced(): void
    {
        self::bootKernel();

        $connection = self::getContainer()->get(Connection::class);
        if ('sqlite' !== $connection->getDatabasePlatform()->getName()) {
            self::markTestSkipped('SQLite-specific connection invariant.');
        }

        self::assertSame(1, (int) $connection->fetchOne('PRAGMA foreign_keys'));

        $foreignKey = $connection->fetchAssociative("SELECT * FROM pragma_foreign_key_list('cards')");
        self::assertSame('addressbooks', $foreignKey['table']);
        self::assertSame('addressbookid', $foreignKey['from']);
        self::assertSame('CASCADE', $foreignKey['on_delete']);
    }
}
