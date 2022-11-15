<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add default value for calendar order.
 */
final class Version20210928132307 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add default value for calendar order.';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'This migration is specific to \'mysql\'. Skipping it is fine.');

        $this->addSql('ALTER TABLE calendarinstances CHANGE calendarorder calendarorder INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->skipIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'This migration is specific to \'mysql\'. Skipping it is fine.');

        $this->addSql('ALTER TABLE calendarinstances CHANGE calendarorder calendarorder INT NOT NULL');
    }
}
