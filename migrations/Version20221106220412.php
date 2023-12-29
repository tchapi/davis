<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * PostgreSQL - Initial migration: Create all necessary sabre/dav tables.
 */
final class Version20221106220412 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '[PostgreSQL] Initial migration: Create all necessary sabre/dav tables';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'This migration is specific to \'postgresql\'. Skipping it is fine.');

        $this->addSql('CREATE SEQUENCE addressbooks_id_seq INCREMENT BY 1 MINVALUE 1 START 1;');
        $this->addSql('CREATE SEQUENCE calendars_id_seq INCREMENT BY 1 MINVALUE 1 START 1;');
        $this->addSql('CREATE SEQUENCE cards_id_seq INCREMENT BY 1 MINVALUE 1 START 1;');
        $this->addSql('CREATE SEQUENCE calendarsubscriptions_id_seq INCREMENT BY 1 MINVALUE 1 START 1;');
        $this->addSql('CREATE SEQUENCE schedulingobjects_id_seq INCREMENT BY 1 MINVALUE 1 START 1;');
        $this->addSql('CREATE SEQUENCE locks_id_seq INCREMENT BY 1 MINVALUE 1 START 1;');
        $this->addSql('CREATE SEQUENCE calendarinstances_id_seq INCREMENT BY 1 MINVALUE 1 START 1;');
        $this->addSql('CREATE SEQUENCE addressbookchanges_id_seq INCREMENT BY 1 MINVALUE 1 START 1;');
        $this->addSql('CREATE SEQUENCE principals_id_seq INCREMENT BY 1 MINVALUE 1 START 1;');
        $this->addSql('CREATE SEQUENCE calendarchanges_id_seq INCREMENT BY 1 MINVALUE 1 START 1;');
        $this->addSql('CREATE SEQUENCE users_id_seq INCREMENT BY 1 MINVALUE 1 START 1;');
        $this->addSql('CREATE SEQUENCE calendarobjects_id_seq INCREMENT BY 1 MINVALUE 1 START 1;');
        $this->addSql('CREATE SEQUENCE propertystorage_id_seq INCREMENT BY 1 MINVALUE 1 START 1;');
        $this->addSql('CREATE TABLE addressbooks (id INT NOT NULL, principaluri VARCHAR(255) NOT NULL, displayname VARCHAR(255) NOT NULL, uri VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, synctoken VARCHAR(255) NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE TABLE calendars (id INT NOT NULL, synctoken VARCHAR(255) NOT NULL, components VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE TABLE cards (id INT NOT NULL, addressbookid INT NOT NULL, carddata BYTEA DEFAULT NULL, uri VARCHAR(255) DEFAULT NULL, lastmodified INT DEFAULT NULL, etag VARCHAR(32) DEFAULT NULL, size INT NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE INDEX IDX_4C258FD8B26C2E9 ON cards (addressbookid);');
        $this->addSql('CREATE TABLE calendarsubscriptions (id INT NOT NULL, uri VARCHAR(255) NOT NULL, principaluri VARCHAR(255) NOT NULL, source TEXT DEFAULT NULL, displayname VARCHAR(255) DEFAULT NULL, refreshrate VARCHAR(10) DEFAULT NULL, calendarorder INT NOT NULL, calendarcolor VARCHAR(10) DEFAULT NULL, striptodos SMALLINT DEFAULT NULL, stripalarms SMALLINT DEFAULT NULL, stripattachments SMALLINT DEFAULT NULL, lastmodified INT DEFAULT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE TABLE schedulingobjects (id INT NOT NULL, principaluri VARCHAR(255) DEFAULT NULL, calendardata BYTEA DEFAULT NULL, uri VARCHAR(255) DEFAULT NULL, lastmodified INT DEFAULT NULL, etag VARCHAR(255) DEFAULT NULL, size INT NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE TABLE locks (id INT NOT NULL, owner VARCHAR(255) DEFAULT NULL, timeout INT DEFAULT NULL, created INT DEFAULT NULL, token VARCHAR(255) DEFAULT NULL, scope SMALLINT DEFAULT NULL, depth SMALLINT DEFAULT NULL, uri VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE TABLE calendarinstances (id INT NOT NULL, calendarid INT NOT NULL, principaluri VARCHAR(255) DEFAULT NULL, access SMALLINT DEFAULT 1 NOT NULL, displayname VARCHAR(255) DEFAULT NULL, uri VARCHAR(255) DEFAULT NULL, description TEXT DEFAULT NULL, calendarorder INT DEFAULT 0 NOT NULL, calendarcolor VARCHAR(10) DEFAULT NULL, timezone TEXT DEFAULT NULL, transparent INT DEFAULT NULL, share_href VARCHAR(255) DEFAULT NULL, share_displayname VARCHAR(255) DEFAULT NULL, share_invitestatus INT DEFAULT 2 NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE INDEX IDX_51856561B8CB7204 ON calendarinstances (calendarid);');
        $this->addSql('CREATE TABLE addressbookchanges (id INT NOT NULL, addressbookid INT NOT NULL, uri VARCHAR(255) NOT NULL, synctoken VARCHAR(255) NOT NULL, operation INT NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE INDEX IDX_EB122CD58B26C2E9 ON addressbookchanges (addressbookid);');
        $this->addSql('CREATE TABLE principals (id INT NOT NULL, uri VARCHAR(255) NOT NULL, email VARCHAR(255) DEFAULT NULL, displayname VARCHAR(255) DEFAULT NULL, is_main BOOLEAN NOT NULL, is_admin BOOLEAN NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E797E7FB841CB121 ON principals (uri);');
        $this->addSql('CREATE TABLE groupmembers (principal_id INT NOT NULL, member_id INT NOT NULL, PRIMARY KEY(principal_id, member_id));');
        $this->addSql('CREATE INDEX IDX_6F15EDAC474870EE ON groupmembers (principal_id);');
        $this->addSql('CREATE INDEX IDX_6F15EDAC7597D3FE ON groupmembers (member_id);');
        $this->addSql('CREATE TABLE calendarchanges (id INT NOT NULL, calendarid INT NOT NULL, uri VARCHAR(255) NOT NULL, synctoken INT NOT NULL, operation SMALLINT NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE INDEX IDX_737547E2B8CB7204 ON calendarchanges (calendarid);');
        $this->addSql('CREATE TABLE users (id INT NOT NULL, username VARCHAR(255) NOT NULL, digesta1 VARCHAR(255) NOT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9F85E0677 ON users (username);');
        $this->addSql('CREATE TABLE calendarobjects (id INT NOT NULL, calendarid INT NOT NULL, calendardata BYTEA DEFAULT NULL, uri VARCHAR(255) DEFAULT NULL, lastmodified INT DEFAULT NULL, etag VARCHAR(255) DEFAULT NULL, size INT NOT NULL, componenttype VARCHAR(255) DEFAULT NULL, firstoccurence INT DEFAULT NULL, lastoccurence INT DEFAULT NULL, uid VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id));');
        $this->addSql('CREATE INDEX IDX_E14F332CB8CB7204 ON calendarobjects (calendarid);');
        $this->addSql('CREATE TABLE propertystorage (id INT NOT NULL, path VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, valuetype INT DEFAULT NULL, value TEXT DEFAULT NULL, PRIMARY KEY(id));');
        $this->addSql('ALTER TABLE cards ADD CONSTRAINT FK_4C258FD8B26C2E9 FOREIGN KEY (addressbookid) REFERENCES addressbooks (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE calendarinstances ADD CONSTRAINT FK_51856561B8CB7204 FOREIGN KEY (calendarid) REFERENCES calendars (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE addressbookchanges ADD CONSTRAINT FK_EB122CD58B26C2E9 FOREIGN KEY (addressbookid) REFERENCES addressbooks (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE groupmembers ADD CONSTRAINT FK_6F15EDAC474870EE FOREIGN KEY (principal_id) REFERENCES principals (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE groupmembers ADD CONSTRAINT FK_6F15EDAC7597D3FE FOREIGN KEY (member_id) REFERENCES principals (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE calendarchanges ADD CONSTRAINT FK_737547E2B8CB7204 FOREIGN KEY (calendarid) REFERENCES calendars (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
        $this->addSql('ALTER TABLE calendarobjects ADD CONSTRAINT FK_E14F332CB8CB7204 FOREIGN KEY (calendarid) REFERENCES calendars (id) NOT DEFERRABLE INITIALLY IMMEDIATE;');
    }

    public function down(Schema $schema): void
    {
        $this->skipIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'This migration is specific to \'postgresql\'. Skipping it is fine.');

        $this->addSql('ALTER TABLE cards DROP CONSTRAINT FK_4C258FD8B26C2E9;');
        $this->addSql('ALTER TABLE calendarinstances DROP CONSTRAINT FK_51856561B8CB7204;');
        $this->addSql('ALTER TABLE addressbookchanges DROP CONSTRAINT FK_EB122CD58B26C2E9;');
        $this->addSql('ALTER TABLE groupmembers DROP CONSTRAINT FK_6F15EDAC474870EE;');
        $this->addSql('ALTER TABLE groupmembers DROP CONSTRAINT FK_6F15EDAC7597D3FE;');
        $this->addSql('ALTER TABLE calendarchanges DROP CONSTRAINT FK_737547E2B8CB7204;');
        $this->addSql('ALTER TABLE calendarobjects DROP CONSTRAINT FK_E14F332CB8CB7204;');
        $this->addSql('DROP SEQUENCE addressbooks_id_seq CASCADE;');
        $this->addSql('DROP SEQUENCE calendars_id_seq CASCADE;');
        $this->addSql('DROP SEQUENCE cards_id_seq CASCADE;');
        $this->addSql('DROP SEQUENCE calendarsubscriptions_id_seq CASCADE;');
        $this->addSql('DROP SEQUENCE schedulingobjects_id_seq CASCADE;');
        $this->addSql('DROP SEQUENCE locks_id_seq CASCADE;');
        $this->addSql('DROP SEQUENCE calendarinstances_id_seq CASCADE;');
        $this->addSql('DROP SEQUENCE addressbookchanges_id_seq CASCADE;');
        $this->addSql('DROP SEQUENCE principals_id_seq CASCADE;');
        $this->addSql('DROP SEQUENCE calendarchanges_id_seq CASCADE;');
        $this->addSql('DROP SEQUENCE users_id_seq CASCADE;');
        $this->addSql('DROP SEQUENCE calendarobjects_id_seq CASCADE;');
        $this->addSql('DROP SEQUENCE propertystorage_id_seq CASCADE;');
        $this->addSql('DROP TABLE addressbooks;');
        $this->addSql('DROP TABLE calendars;');
        $this->addSql('DROP TABLE cards;');
        $this->addSql('DROP TABLE calendarsubscriptions;');
        $this->addSql('DROP TABLE schedulingobjects;');
        $this->addSql('DROP TABLE locks;');
        $this->addSql('DROP TABLE calendarinstances;');
        $this->addSql('DROP TABLE addressbookchanges;');
        $this->addSql('DROP TABLE principals;');
        $this->addSql('DROP TABLE groupmembers;');
        $this->addSql('DROP TABLE calendarchanges;');
        $this->addSql('DROP TABLE users;');
        $this->addSql('DROP TABLE calendarobjects;');
        $this->addSql('DROP TABLE propertystorage;');
    }
}
