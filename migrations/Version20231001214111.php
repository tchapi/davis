<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Change BLOB to TEXT (https://github.com/tchapi/davis/issues/110).
 */
final class Version20231001214111 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change BLOB to TEXT';
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

        $this->addSql('ALTER TABLE schedulingobjects CHANGE calendardata calendardata LONGBLOB DEFAULT NULL');
        $this->addSql('ALTER TABLE calendarobjects CHANGE calendardata calendardata LONGBLOB DEFAULT NULL');
        $this->addSql('ALTER TABLE cards CHANGE carddata carddata LONGBLOB DEFAULT NULL');
    }
}
