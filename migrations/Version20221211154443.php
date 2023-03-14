<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * SQLite - Initial migration: Create all necessary sabre/dav tables.
 */
final class Version20221211154443 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '[SQLite] Initial migration: Create all necessary sabre/dav tables';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf('sqlite' !== $this->connection->getDatabasePlatform()->getName(), 'This migration is specific to \'sqlite\'. Skipping it is fine.');

        $this->addSql('CREATE TABLE addressbookchanges (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, addressbookid INTEGER NOT NULL, uri VARCHAR(255) NOT NULL, synctoken VARCHAR(255) NOT NULL, operation INTEGER NOT NULL)');
        $this->addSql('CREATE INDEX IDX_EB122CD58B26C2E9 ON addressbookchanges (addressbookid)');
        $this->addSql('CREATE TABLE addressbooks (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, principaluri VARCHAR(255) NOT NULL, displayname VARCHAR(255) NOT NULL, uri VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, synctoken VARCHAR(255) NOT NULL)');
        $this->addSql('CREATE TABLE calendarchanges (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, calendarid INTEGER NOT NULL, uri VARCHAR(255) NOT NULL, synctoken INTEGER NOT NULL, operation SMALLINT NOT NULL)');
        $this->addSql('CREATE INDEX IDX_737547E2B8CB7204 ON calendarchanges (calendarid)');
        $this->addSql('CREATE TABLE calendarinstances (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, calendarid INTEGER NOT NULL, principaluri VARCHAR(255) DEFAULT NULL, access SMALLINT DEFAULT 1 NOT NULL, displayname VARCHAR(255) DEFAULT NULL, uri VARCHAR(255) DEFAULT NULL, description CLOB DEFAULT NULL, calendarorder INTEGER DEFAULT 0 NOT NULL, calendarcolor VARCHAR(10) DEFAULT NULL, timezone CLOB DEFAULT NULL, transparent INTEGER DEFAULT NULL, share_href VARCHAR(255) DEFAULT NULL, share_displayname VARCHAR(255) DEFAULT NULL, share_invitestatus INTEGER DEFAULT 2 NOT NULL)');
        $this->addSql('CREATE INDEX IDX_51856561B8CB7204 ON calendarinstances (calendarid)');
        $this->addSql('CREATE TABLE calendarobjects (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, calendarid INTEGER NOT NULL, calendardata BLOB DEFAULT NULL, uri VARCHAR(255) DEFAULT NULL, lastmodified INTEGER DEFAULT NULL, etag VARCHAR(255) DEFAULT NULL, size INTEGER NOT NULL, componenttype VARCHAR(255) DEFAULT NULL, firstoccurence INTEGER DEFAULT NULL, lastoccurence INTEGER DEFAULT NULL, uid VARCHAR(255) DEFAULT NULL)');
        $this->addSql('CREATE INDEX IDX_E14F332CB8CB7204 ON calendarobjects (calendarid)');
        $this->addSql('CREATE TABLE calendars (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, synctoken VARCHAR(255) NOT NULL, components VARCHAR(255) DEFAULT NULL)');
        $this->addSql('CREATE TABLE calendarsubscriptions (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, uri VARCHAR(255) NOT NULL, principaluri VARCHAR(255) NOT NULL, source CLOB DEFAULT NULL, displayname VARCHAR(255) DEFAULT NULL, refreshrate VARCHAR(10) DEFAULT NULL, calendarorder INTEGER NOT NULL, calendarcolor VARCHAR(10) DEFAULT NULL, striptodos SMALLINT DEFAULT NULL, stripalarms SMALLINT DEFAULT NULL, stripattachments SMALLINT DEFAULT NULL, lastmodified INTEGER DEFAULT NULL)');
        $this->addSql('CREATE TABLE cards (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, addressbookid INTEGER NOT NULL, carddata BLOB DEFAULT NULL, uri VARCHAR(255) DEFAULT NULL, lastmodified INTEGER DEFAULT NULL, etag VARCHAR(32) DEFAULT NULL, size INTEGER NOT NULL)');
        $this->addSql('CREATE INDEX IDX_4C258FD8B26C2E9 ON cards (addressbookid)');
        $this->addSql('CREATE TABLE locks (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, owner VARCHAR(255) DEFAULT NULL, timeout INTEGER DEFAULT NULL, created INTEGER DEFAULT NULL, token VARCHAR(255) DEFAULT NULL, scope SMALLINT DEFAULT NULL, depth SMALLINT DEFAULT NULL, uri VARCHAR(255) DEFAULT NULL)');
        $this->addSql('CREATE TABLE principals (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, uri VARCHAR(255) NOT NULL, email VARCHAR(255) DEFAULT NULL, displayname VARCHAR(255) DEFAULT NULL, is_main BOOLEAN NOT NULL, is_admin BOOLEAN NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E797E7FB841CB121 ON principals (uri)');
        $this->addSql('CREATE TABLE groupmembers (principal_id INTEGER NOT NULL, member_id INTEGER NOT NULL, PRIMARY KEY(principal_id, member_id))');
        $this->addSql('CREATE INDEX IDX_6F15EDAC474870EE ON groupmembers (principal_id)');
        $this->addSql('CREATE INDEX IDX_6F15EDAC7597D3FE ON groupmembers (member_id)');
        $this->addSql('CREATE TABLE propertystorage (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, path VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, valuetype INTEGER DEFAULT NULL, value CLOB DEFAULT NULL)');
        $this->addSql('CREATE TABLE schedulingobjects (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, principaluri VARCHAR(255) DEFAULT NULL, calendardata BLOB DEFAULT NULL, uri VARCHAR(255) DEFAULT NULL, lastmodified INTEGER DEFAULT NULL, etag VARCHAR(255) DEFAULT NULL, size INTEGER NOT NULL)');
        $this->addSql('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, username VARCHAR(255) NOT NULL, digesta1 VARCHAR(255) NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9F85E0677 ON users (username)');
    }

    public function down(Schema $schema): void
    {
        $this->skipIf('sqlite' !== $this->connection->getDatabasePlatform()->getName(), 'This migration is specific to \'sqlite\'. Skipping it is fine.');

        $this->addSql('DROP TABLE addressbookchanges');
        $this->addSql('DROP TABLE addressbooks');
        $this->addSql('DROP TABLE calendarchanges');
        $this->addSql('DROP TABLE calendarinstances');
        $this->addSql('DROP TABLE calendarobjects');
        $this->addSql('DROP TABLE calendars');
        $this->addSql('DROP TABLE calendarsubscriptions');
        $this->addSql('DROP TABLE cards');
        $this->addSql('DROP TABLE locks');
        $this->addSql('DROP TABLE principals');
        $this->addSql('DROP TABLE groupmembers');
        $this->addSql('DROP TABLE propertystorage');
        $this->addSql('DROP TABLE schedulingobjects');
        $this->addSql('DROP TABLE users');
    }
}
