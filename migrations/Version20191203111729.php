<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Make Address book's description nullable.
 */
final class Version20191203111729 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make Address book\'s description nullable';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'This migration is specific to \'mysql\'. Skipping it is fine.');

        $this->addSql('ALTER TABLE addressbooks CHANGE description description LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->skipIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'This migration is specific to \'mysql\'. Skipping it is fine.');

        // Since up() made the column nullable, address books created in the meantime may have
        // no description at all; they would violate the restored NOT NULL.
        $this->addSql("UPDATE addressbooks SET description = '' WHERE description IS NULL");
        $this->addSql('ALTER TABLE addressbooks CHANGE description description LONGTEXT NOT NULL');
    }
}
