<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 *  Scale timestamps to big int for the Year 2038 problem
 */
final class Version20250409193948 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Scale timestamps to big int for the Year 2038 problem';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf('sqlite' === $this->connection->getDatabasePlatform()->getName(), 'This migration is not needed on \'sqlite\'. Skipping it is fine.');

        // MySQL
        if ('mysql' === $this->connection->getDatabasePlatform()->getName()) {
            $this->addSql('ALTER TABLE calendarobjects CHANGE lastmodified lastmodified BIGINT DEFAULT NULL, CHANGE firstoccurence firstoccurence BIGINT DEFAULT NULL, CHANGE lastoccurence lastoccurence BIGINT DEFAULT NULL');
            $this->addSql('ALTER TABLE calendarsubscriptions CHANGE lastmodified lastmodified BIGINT DEFAULT NULL');
            $this->addSql('ALTER TABLE locks CHANGE created created BIGINT DEFAULT NULL');
            $this->addSql('ALTER TABLE schedulingobjects CHANGE lastmodified lastmodified BIGINT DEFAULT NULL');
        }

        // Posgres
        if ('postgresql' === $this->connection->getDatabasePlatform()->getName()) {
            $this->addSql('ALTER TABLE calendarobjects ALTER COLUMN lastmodified TYPE BIGINT');
            $this->addSql('ALTER TABLE calendarobjects ALTER COLUMN firstoccurence TYPE BIGINT');
            $this->addSql('ALTER TABLE calendarobjects ALTER COLUMN lastoccurence TYPE BIGINT');
            $this->addSql('ALTER TABLE calendarsubscriptions ALTER COLUMN lastmodified TYPE BIGINT');
            $this->addSql('ALTER TABLE locks ALTER COLUMN created TYPE BIGINT');
            $this->addSql('ALTER TABLE schedulingobjects ALTER COLUMN lastmodified TYPE BIGINT');
        }
    }

    public function down(Schema $schema): void
    {
        // No need for a down here, it's fine
    }
}
