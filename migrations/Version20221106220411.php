<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Change VARBINARY to VARCHAR to allow better PostgreSQL support in later migrations.
 */
final class Version20221106220411 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change VARBINARY to VARCHAR to allow better PostgreSQL support in later migrations';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'This migration is specific to \'mysql\'. Skipping it is fine.');

        $this->addSql('ALTER TABLE addressbookchanges CHANGE uri uri VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE addressbooks CHANGE principaluri principaluri VARCHAR(255) NOT NULL, CHANGE uri uri VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE calendarchanges CHANGE uri uri VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE calendarinstances CHANGE principaluri principaluri VARCHAR(255) DEFAULT NULL, CHANGE uri uri VARCHAR(255) DEFAULT NULL, CHANGE calendarcolor calendarcolor VARCHAR(10) DEFAULT NULL, CHANGE share_href share_href VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE calendarobjects CHANGE uri uri VARCHAR(255) DEFAULT NULL, CHANGE etag etag VARCHAR(255) DEFAULT NULL, CHANGE componenttype componenttype VARCHAR(255) DEFAULT NULL, CHANGE uid uid VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE calendars CHANGE components components VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE calendarsubscriptions CHANGE uri uri VARCHAR(255) NOT NULL, CHANGE principaluri principaluri VARCHAR(255) NOT NULL, CHANGE calendarcolor calendarcolor VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE cards CHANGE uri uri VARCHAR(255) DEFAULT NULL, CHANGE etag etag VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE locks CHANGE token token VARCHAR(255) DEFAULT NULL, CHANGE uri uri VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE principals CHANGE uri uri VARCHAR(255) NOT NULL, CHANGE email email VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E797E7FB841CB121 ON principals (uri)');
        $this->addSql('ALTER TABLE schedulingobjects CHANGE principaluri principaluri VARCHAR(255) DEFAULT NULL, CHANGE uri uri VARCHAR(255) DEFAULT NULL, CHANGE etag etag VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users CHANGE digesta1 digesta1 VARCHAR(255) NOT NULL, CHANGE username username VARCHAR(255) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9F85E0677 ON users (username)');
    }

    public function down(Schema $schema): void
    {
        $this->skipIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'This migration is specific to \'mysql\'. Skipping it is fine.');

        $this->addSql('ALTER TABLE addressbookchanges CHANGE uri uri VARBINARY(255) NOT NULL');
        $this->addSql('ALTER TABLE addressbooks CHANGE principaluri principaluri VARBINARY(255) NOT NULL, CHANGE uri uri VARBINARY(255) NOT NULL');
        $this->addSql('ALTER TABLE calendarchanges CHANGE uri uri VARBINARY(255) NOT NULL');
        $this->addSql('ALTER TABLE calendarinstances CHANGE principaluri principaluri VARBINARY(255) DEFAULT NULL, CHANGE uri uri VARBINARY(255) DEFAULT NULL, CHANGE calendarcolor calendarcolor VARBINARY(10) DEFAULT NULL, CHANGE share_href share_href VARBINARY(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE calendarobjects CHANGE uri uri VARBINARY(255) DEFAULT NULL, CHANGE etag etag VARBINARY(255) DEFAULT NULL, CHANGE componenttype componenttype VARBINARY(255) DEFAULT NULL, CHANGE uid uid VARBINARY(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE calendars CHANGE components components VARBINARY(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE calendarsubscriptions CHANGE uri uri VARBINARY(255) NOT NULL, CHANGE principaluri principaluri VARBINARY(255) NOT NULL, CHANGE calendarcolor calendarcolor VARBINARY(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE cards CHANGE uri uri VARBINARY(255) DEFAULT NULL, CHANGE etag etag VARBINARY(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE locks CHANGE token token VARBINARY(255) DEFAULT NULL, CHANGE uri uri VARBINARY(255) DEFAULT NULL');
        $this->addSql('DROP INDEX UNIQ_E797E7FB841CB121 ON principals');
        $this->addSql('ALTER TABLE principals CHANGE uri uri VARBINARY(255) NOT NULL, CHANGE email email VARBINARY(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE schedulingobjects CHANGE principaluri principaluri VARBINARY(255) DEFAULT NULL, CHANGE uri uri VARBINARY(255) DEFAULT NULL, CHANGE etag etag VARBINARY(255) DEFAULT NULL');
        $this->addSql('DROP INDEX UNIQ_1483A5E9F85E0677 ON users');
        $this->addSql('ALTER TABLE users CHANGE digesta1 digesta1 VARBINARY(255) NOT NULL, CHANGE username username VARBINARY(255) NOT NULL');
    }
}
