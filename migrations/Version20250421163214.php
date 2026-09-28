<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add birthday calendar.
 */
final class Version20250421163214 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add birthday calendars';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof AbstractMySQLPlatform) {
            $this->addSql('ALTER TABLE addressbooks ADD included_in_birthday_calendar TINYINT(1) DEFAULT 0');
        } elseif ($platform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE addressbooks ADD COLUMN included_in_birthday_calendar BOOLEAN DEFAULT FALSE;');
        } elseif ($platform instanceof SqlitePlatform) {
            $this->addSql('ALTER TABLE addressbooks ADD COLUMN included_in_birthday_calendar INTEGER DEFAULT 0;');
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            $this->addSql('ALTER TABLE addressbooks DROP included_in_birthday_calendar');
        } else {
            $this->addSql('ALTER TABLE addressbooks DROP COLUMN included_in_birthday_calendar');
        }
    }
}
