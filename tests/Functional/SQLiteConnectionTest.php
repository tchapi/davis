<?php

namespace App\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SQLiteConnectionTest extends KernelTestCase
{
    public function testServerDefaults(): void
    {
        self::bootKernel();

        $connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        if ('sqlite' !== $connection->getDatabasePlatform()->getName()) {
            self::markTestSkipped('SQLite-specific connection defaults.');
        }

        self::assertSame('wal', $connection->fetchOne('PRAGMA journal_mode'));
        self::assertSame(2, (int) $connection->fetchOne('PRAGMA synchronous'));
        self::assertSame(60000, (int) $connection->fetchOne('PRAGMA busy_timeout'));
    }
}
