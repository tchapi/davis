<?php

declare(strict_types=1);

namespace DoctrineMigrations;

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
        $engine = $this->connection->getDatabasePlatform()->getName();

        if ('mysql' === $engine) {
            $this->addSql('ALTER TABLE addressbooks ADD included_in_birthday_calendar TINYINT(1) DEFAULT 0');
        } elseif ('postgresql' === $engine) {
            $this->addSql('ALTER TABLE addressbooks ADD COLUMN included_in_birthday_calendar BOOLEAN DEFAULT FALSE;');
        } elseif ('sqlite' === $engine) {
            $this->addSql('ALTER TABLE addressbooks ADD COLUMN included_in_birthday_calendar INTEGER DEFAULT 0;');
        }
    }

    public function down(Schema $schema): void
    {
        if ('mysql' === $this->connection->getDatabasePlatform()->getName()) {
            $this->addSql('ALTER TABLE addressbooks DROP included_in_birthday_calendar');
        } else {
            $this->addSql('ALTER TABLE addressbooks DROP COLUMN included_in_birthday_calendar');
        }
    }
}
