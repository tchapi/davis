<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Version20250409193948 scaled every timestamp to BIGINT for the Year 2038 problem except this one.
 */
final class Version20260930120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Scale cards.lastmodified to big int for the Year 2038 problem';
    }

    public function up(Schema $schema): void
    {
        // SQLite stores every INTEGER as a 64-bit value, so it was never affected
        if ($this->connection->getDatabasePlatform() instanceof SqlitePlatform) {
            return;
        }

        if ($this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            $this->addSql('ALTER TABLE cards CHANGE lastmodified lastmodified BIGINT DEFAULT NULL');
        }

        if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE cards ALTER COLUMN lastmodified TYPE BIGINT');
        }
    }

    public function down(Schema $schema): void
    {
        // Narrowing back to INT would truncate post-2038 timestamps, like Version20250409193948
    }
}
