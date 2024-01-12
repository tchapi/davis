<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Scale up to MEDIUMTEXT for calendar and card data https://github.com/tchapi/davis/pull/111#issuecomment-1872295498
 * ⚠️ This does not fail if the column is already a MEDIUM TEXT, which has allowed us to change a previous migration
 * without touching this one (see https://github.com/tchapi/davis/issues/128)
 */
final class Version20231229203515 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Scale up to MEDIUMTEXT for calendar and card data';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'This migration is specific to \'mysql\'. Skipping it is fine.');

        $this->addSql('ALTER TABLE calendarobjects CHANGE calendardata calendardata MEDIUMTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE cards CHANGE carddata carddata MEDIUMTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE schedulingobjects CHANGE calendardata calendardata MEDIUMTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->skipIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'This migration is specific to \'mysql\'. Skipping it is fine.');

        $this->addSql('ALTER TABLE schedulingobjects CHANGE calendardata calendardata TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE calendarobjects CHANGE calendardata calendardata TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE cards CHANGE carddata carddata TEXT DEFAULT NULL');
    }
}
