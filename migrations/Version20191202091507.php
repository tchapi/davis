<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The default access value and share_invitestatus value should be set on the DB level, and timezone should be a text column.
 */
final class Version20191202091507 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The default access value and share_invitestatus value should be set on the DB level, and timezone should be a text column.';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'This migration is specific to \'mysql\'. Skipping it is fine.');

        $this->addSql('ALTER TABLE calendarinstances CHANGE access access SMALLINT DEFAULT 1 NOT NULL, CHANGE share_invitestatus share_invitestatus INT DEFAULT 2 NOT NULL, CHANGE timezone timezone LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->skipIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'This migration is specific to \'mysql\'. Skipping it is fine.');

        $this->addSql('ALTER TABLE calendarinstances CHANGE access access SMALLINT NOT NULL, CHANGE share_invitestatus share_invitestatus INT NOT NULL, CHANGE timezone timezone LONGTEXT DEFAULT NULL, CHANGE timezone timezone VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_unicode_ci`');
    }
}
