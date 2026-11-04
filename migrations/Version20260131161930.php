<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add public flag on calendar instance.
 */
final class Version20260131161930 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add public flag on CalendarInstance (replacing ACCESS_PUBLIC flag)';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof AbstractMySQLPlatform) {
            $this->addSql('ALTER TABLE calendarinstances ADD public TINYINT(1) DEFAULT 0 NOT NULL');
        } elseif ($platform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE calendarinstances ADD public BOOLEAN DEFAULT FALSE NOT NULL');
        } elseif ($platform instanceof SqlitePlatform) {
            $this->addSql('ALTER TABLE calendarinstances ADD public BOOLEAN DEFAULT 0 NOT NULL');
        }

        // Migrate ACCESS_PUBLIC (10) to ACCESS_SHAREDOWNER (1) + public = true
        if ($platform instanceof PostgreSQLPlatform) {
            $this->addSql('UPDATE calendarinstances SET public = TRUE, access = 1 WHERE access = 10');
        } else {
            // MySQL and SQLite accept 1/0 for booleans
            $this->addSql('UPDATE calendarinstances SET public = 1, access = 1 WHERE access = 10');
        }
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        // Revert public = true back to ACCESS_PUBLIC (10)
        if ($platform instanceof PostgreSQLPlatform) {
            $this->addSql('UPDATE calendarinstances SET access = 10 WHERE public = TRUE');
        } else {
            $this->addSql('UPDATE calendarinstances SET access = 10 WHERE public = 1');
        }

        if ($platform instanceof AbstractMySQLPlatform) {
            $this->addSql('ALTER TABLE calendarinstances DROP public');
        } elseif ($platform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE calendarinstances DROP COLUMN public');
        } elseif ($platform instanceof SqlitePlatform) {
            $this->addSql('ALTER TABLE calendarinstances DROP COLUMN public');
        }
    }
}
