<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * [SQLite] Change BLOB to TEXT (https://github.com/tchapi/davis/issues/110).
 */
final class Version20231001214113 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '[SQLite] Change BLOB to TEXT';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf('sqlite' !== $this->connection->getDatabasePlatform()->getName(), 'This migration is specific to \'sqlite\'. Skipping it is fine.');

        $this->addSql('ALTER TABLE calendarobjects ADD COLUMN new_calendardata TEXT DEFAULT NULL;');
        $this->addSql('UPDATE calendarobjects SET new_calendardata = CAST(calendardata as TEXT);');
        $this->addSql('ALTER TABLE calendarobjects RENAME COLUMN calendardata TO old_calendardata;');
        $this->addSql('ALTER TABLE calendarobjects RENAME COLUMN new_calendardata TO calendardata;');
        $this->addSql('ALTER TABLE calendarobjects DROP COLUMN old_calendardata;');

        $this->addSql('ALTER TABLE cards ADD COLUMN new_carddata TEXT DEFAULT NULL;');
        $this->addSql('UPDATE cards SET new_carddata = CAST(carddata as TEXT);');
        $this->addSql('ALTER TABLE cards RENAME COLUMN carddata TO old_carddata;');
        $this->addSql('ALTER TABLE cards RENAME COLUMN new_carddata TO carddata;');
        $this->addSql('ALTER TABLE cards DROP COLUMN old_carddata;');

        $this->addSql('ALTER TABLE schedulingobjects ADD COLUMN new_calendardata TEXT DEFAULT NULL;');
        $this->addSql('UPDATE schedulingobjects SET new_calendardata = CAST(calendardata as TEXT);');
        $this->addSql('ALTER TABLE schedulingobjects RENAME COLUMN calendardata TO old_calendardata;');
        $this->addSql('ALTER TABLE schedulingobjects RENAME COLUMN new_calendardata TO calendardata;');
        $this->addSql('ALTER TABLE schedulingobjects DROP COLUMN old_calendardata;');
    }

    public function down(Schema $schema): void
    {
        $this->skipIf('sqlite' !== $this->connection->getDatabasePlatform()->getName(), 'This migration is specific to \'sqlite\'. Skipping it is fine.');

        $this->addSql('ALTER TABLE calendarobjects ADD COLUMN new_calendardata BLOB DEFAULT NULL;');
        $this->addSql('UPDATE calendarobjects SET new_calendardata = CAST(calendardata as BLOB);');
        $this->addSql('ALTER TABLE calendarobjects RENAME COLUMN calendardata TO old_calendardata;');
        $this->addSql('ALTER TABLE calendarobjects RENAME COLUMN new_calendardata TO calendardata;');
        $this->addSql('ALTER TABLE calendarobjects DROP COLUMN old_calendardata;');

        $this->addSql('ALTER TABLE cards ADD COLUMN new_carddata BLOB DEFAULT NULL;');
        $this->addSql('UPDATE cards SET new_carddata = CAST(carddata as BLOB);');
        $this->addSql('ALTER TABLE cards RENAME COLUMN carddata TO old_carddata;');
        $this->addSql('ALTER TABLE cards RENAME COLUMN new_carddata TO carddata;');
        $this->addSql('ALTER TABLE cards DROP COLUMN old_carddata;');

        $this->addSql('ALTER TABLE schedulingobjects ADD COLUMN new_calendardata BLOB DEFAULT NULL;');
        $this->addSql('UPDATE schedulingobjects SET new_calendardata = CAST(calendardata as BLOB);');
        $this->addSql('ALTER TABLE schedulingobjects RENAME COLUMN calendardata TO old_calendardata;');
        $this->addSql('ALTER TABLE schedulingobjects RENAME COLUMN new_calendardata TO calendardata;');
        $this->addSql('ALTER TABLE schedulingobjects DROP COLUMN old_calendardata;');
    }
}
