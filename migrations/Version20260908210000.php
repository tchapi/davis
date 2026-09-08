<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Give calendarsubscriptions.calendarorder a default value.
 */
final class Version20260908210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Default calendarsubscriptions.calendarorder to 0, as sabre/dav omits the column when a client subscribes without a calendar-order';
    }

    public function up(Schema $schema): void
    {
        $engine = $this->connection->getDatabasePlatform()->getName();

        // \Sabre\CalDAV\Backend\PDO::createSubscription() only lists `calendarorder` in its
        // INSERT when the client sent {http://apple.com/ns/ical/}calendar-order. Without a
        // default, subscribing to a feed then fails with a NOT NULL violation (HTTP 500).
        if ('mysql' === $engine) {
            $this->addSql('ALTER TABLE calendarsubscriptions CHANGE calendarorder calendarorder INT DEFAULT 0 NOT NULL');
        } elseif ('postgresql' === $engine) {
            $this->addSql('ALTER TABLE calendarsubscriptions ALTER COLUMN calendarorder SET DEFAULT 0');
        } elseif ('sqlite' === $engine) {
            // SQLite cannot alter a column in place: add the replacement, copy, swap, drop.
            $this->addSql('ALTER TABLE calendarsubscriptions ADD COLUMN new_calendarorder INTEGER DEFAULT 0 NOT NULL');
            $this->addSql('UPDATE calendarsubscriptions SET new_calendarorder = calendarorder');
            $this->addSql('ALTER TABLE calendarsubscriptions RENAME COLUMN calendarorder TO old_calendarorder');
            $this->addSql('ALTER TABLE calendarsubscriptions RENAME COLUMN new_calendarorder TO calendarorder');
            $this->addSql('ALTER TABLE calendarsubscriptions DROP COLUMN old_calendarorder');
        }
    }

    public function down(Schema $schema): void
    {
        $engine = $this->connection->getDatabasePlatform()->getName();

        if ('mysql' === $engine) {
            $this->addSql('ALTER TABLE calendarsubscriptions CHANGE calendarorder calendarorder INT NOT NULL');
        } elseif ('postgresql' === $engine) {
            $this->addSql('ALTER TABLE calendarsubscriptions ALTER COLUMN calendarorder DROP DEFAULT');
        } elseif ('sqlite' === $engine) {
            // SQLite refuses to ADD a NOT NULL column without a default, so the only way back
            // is to rebuild the table with its original definition.
            $this->addSql('CREATE TABLE calendarsubscriptions_old (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, uri VARCHAR(255) NOT NULL, principaluri VARCHAR(255) NOT NULL, source CLOB DEFAULT NULL, displayname VARCHAR(255) DEFAULT NULL, refreshrate VARCHAR(10) DEFAULT NULL, calendarorder INTEGER NOT NULL, calendarcolor VARCHAR(10) DEFAULT NULL, striptodos SMALLINT DEFAULT NULL, stripalarms SMALLINT DEFAULT NULL, stripattachments SMALLINT DEFAULT NULL, lastmodified INTEGER DEFAULT NULL)');
            $this->addSql('INSERT INTO calendarsubscriptions_old (id, uri, principaluri, source, displayname, refreshrate, calendarorder, calendarcolor, striptodos, stripalarms, stripattachments, lastmodified) SELECT id, uri, principaluri, source, displayname, refreshrate, calendarorder, calendarcolor, striptodos, stripalarms, stripattachments, lastmodified FROM calendarsubscriptions');
            $this->addSql('DROP TABLE calendarsubscriptions');
            $this->addSql('ALTER TABLE calendarsubscriptions_old RENAME TO calendarsubscriptions');
        }
    }
}
