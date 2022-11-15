<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Create all necessary sabre/dav tables.
 */
final class Version20191030113307 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create all necessary sabre/dav tables';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'This migration is specific to \'mysql\'. Skipping it is fine.');

        $this->addSql('CREATE TABLE cards (id INT AUTO_INCREMENT NOT NULL, addressbookid INT NOT NULL, carddata LONGBLOB DEFAULT NULL, uri VARBINARY(255) DEFAULT NULL, lastmodified INT DEFAULT NULL, etag VARBINARY(32) DEFAULT NULL, size INT NOT NULL, INDEX IDX_4C258FD8B26C2E9 (addressbookid), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE principals (id INT AUTO_INCREMENT NOT NULL, uri VARBINARY(255) NOT NULL, email VARBINARY(255) DEFAULT NULL, displayname VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE locks (id INT AUTO_INCREMENT NOT NULL, owner VARCHAR(255) DEFAULT NULL, timeout INT DEFAULT NULL, created INT DEFAULT NULL, token VARBINARY(255) DEFAULT NULL, scope SMALLINT DEFAULT NULL, depth SMALLINT DEFAULT NULL, uri VARBINARY(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE propertystorage (id INT AUTO_INCREMENT NOT NULL, path VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, valuetype INT DEFAULT NULL, value LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, username VARBINARY(255) NOT NULL, digesta1 VARBINARY(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE calendarobjects (id INT AUTO_INCREMENT NOT NULL, calendarid INT NOT NULL, calendardata LONGBLOB DEFAULT NULL, uri VARBINARY(255) DEFAULT NULL, lastmodified INT DEFAULT NULL, etag VARBINARY(255) DEFAULT NULL, size INT NOT NULL, componenttype VARBINARY(255) DEFAULT NULL, firstoccurence INT DEFAULT NULL, lastoccurence INT DEFAULT NULL, uid VARBINARY(255) DEFAULT NULL, INDEX IDX_E14F332CB8CB7204 (calendarid), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE addressbooks (id INT AUTO_INCREMENT NOT NULL, principaluri VARBINARY(255) NOT NULL, displayname VARCHAR(255) NOT NULL, uri VARBINARY(255) NOT NULL, description LONGTEXT NOT NULL, synctoken VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE calendarsubscriptions (id INT AUTO_INCREMENT NOT NULL, uri VARBINARY(255) NOT NULL, principaluri VARBINARY(255) NOT NULL, source LONGTEXT DEFAULT NULL, displayname VARCHAR(255) DEFAULT NULL, refreshrate VARCHAR(10) DEFAULT NULL, calendarorder INT NOT NULL, calendarcolor VARBINARY(10) DEFAULT NULL, striptodos SMALLINT DEFAULT NULL, stripalarms SMALLINT DEFAULT NULL, stripattachments SMALLINT DEFAULT NULL, lastmodified INT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE schedulingobjects (id INT AUTO_INCREMENT NOT NULL, principaluri VARBINARY(255) DEFAULT NULL, calendardata LONGBLOB DEFAULT NULL, uri VARBINARY(255) DEFAULT NULL, lastmodified INT DEFAULT NULL, etag VARBINARY(255) DEFAULT NULL, size INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE calendarinstances (id INT AUTO_INCREMENT NOT NULL, calendarid INT NOT NULL, principaluri VARBINARY(255) DEFAULT NULL, access SMALLINT NOT NULL, displayname VARCHAR(255) DEFAULT NULL, uri VARBINARY(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, calendarorder INT NOT NULL, calendarcolor VARBINARY(10) DEFAULT NULL, timezone VARCHAR(255) DEFAULT NULL, transparent INT DEFAULT NULL, share_href VARBINARY(255) DEFAULT NULL, share_displayname VARCHAR(255) DEFAULT NULL, share_invitestatus INT NOT NULL, INDEX IDX_51856561B8CB7204 (calendarid), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE calendars (id INT AUTO_INCREMENT NOT NULL, synctoken VARCHAR(255) NOT NULL, components VARBINARY(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE calendarchanges (id INT AUTO_INCREMENT NOT NULL, calendarid INT NOT NULL, uri VARBINARY(255) NOT NULL, synctoken INT NOT NULL, operation SMALLINT NOT NULL, INDEX IDX_737547E2B8CB7204 (calendarid), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE addressbookchanges (id INT AUTO_INCREMENT NOT NULL, addressbookid INT NOT NULL, uri VARBINARY(255) NOT NULL, synctoken VARCHAR(255) NOT NULL, operation INT NOT NULL, INDEX IDX_EB122CD58B26C2E9 (addressbookid), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE cards ADD CONSTRAINT FK_4C258FD8B26C2E9 FOREIGN KEY (addressbookid) REFERENCES addressbooks (id)');
        $this->addSql('ALTER TABLE calendarobjects ADD CONSTRAINT FK_E14F332CB8CB7204 FOREIGN KEY (calendarid) REFERENCES calendars (id)');
        $this->addSql('ALTER TABLE calendarinstances ADD CONSTRAINT FK_51856561B8CB7204 FOREIGN KEY (calendarid) REFERENCES calendars (id)');
        $this->addSql('ALTER TABLE calendarchanges ADD CONSTRAINT FK_737547E2B8CB7204 FOREIGN KEY (calendarid) REFERENCES calendars (id)');
        $this->addSql('ALTER TABLE addressbookchanges ADD CONSTRAINT FK_EB122CD58B26C2E9 FOREIGN KEY (addressbookid) REFERENCES addressbooks (id)');
    }

    public function down(Schema $schema): void
    {
        $this->skipIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'This migration is specific to \'mysql\'. Skipping it is fine.');

        $this->addSql('ALTER TABLE cards DROP FOREIGN KEY FK_4C258FD8B26C2E9');
        $this->addSql('ALTER TABLE addressbookchanges DROP FOREIGN KEY FK_EB122CD58B26C2E9');
        $this->addSql('ALTER TABLE calendarobjects DROP FOREIGN KEY FK_E14F332CB8CB7204');
        $this->addSql('ALTER TABLE calendarinstances DROP FOREIGN KEY FK_51856561B8CB7204');
        $this->addSql('ALTER TABLE calendarchanges DROP FOREIGN KEY FK_737547E2B8CB7204');
        $this->addSql('DROP TABLE cards');
        $this->addSql('DROP TABLE principals');
        $this->addSql('DROP TABLE locks');
        $this->addSql('DROP TABLE propertystorage');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE calendarobjects');
        $this->addSql('DROP TABLE addressbooks');
        $this->addSql('DROP TABLE calendarsubscriptions');
        $this->addSql('DROP TABLE schedulingobjects');
        $this->addSql('DROP TABLE calendarinstances');
        $this->addSql('DROP TABLE calendars');
        $this->addSql('DROP TABLE calendarchanges');
        $this->addSql('DROP TABLE addressbookchanges');
    }
}
