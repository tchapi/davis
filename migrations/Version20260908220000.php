<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Allow addressbooks.displayname to be null.
 */
final class Version20260908220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow addressbooks.displayname to be null, as a display name is optional when a client creates an address book';
    }

    public function up(Schema $schema): void
    {
        $engine = $this->connection->getDatabasePlatform()->getName();

        // A display name is optional in CardDAV: \Sabre\CardDAV\Backend\PDO::createAddressBook()
        // binds NULL when the client's MKCOL carries no {DAV:}displayname, and updateAddressBook()
        // does the same when a PROPPATCH removes it. With a NOT NULL column both fail (HTTP 500).
        if ('mysql' === $engine) {
            $this->addSql('ALTER TABLE addressbooks CHANGE displayname displayname VARCHAR(255) DEFAULT NULL');
        } elseif ('postgresql' === $engine) {
            $this->addSql('ALTER TABLE addressbooks ALTER COLUMN displayname DROP NOT NULL');
        } elseif ('sqlite' === $engine) {
            // SQLite cannot alter a column in place: add the replacement, copy, swap, drop.
            $this->addSql('ALTER TABLE addressbooks ADD COLUMN new_displayname VARCHAR(255) DEFAULT NULL');
            $this->addSql('UPDATE addressbooks SET new_displayname = displayname');
            $this->addSql('ALTER TABLE addressbooks RENAME COLUMN displayname TO old_displayname');
            $this->addSql('ALTER TABLE addressbooks RENAME COLUMN new_displayname TO displayname');
            $this->addSql('ALTER TABLE addressbooks DROP COLUMN old_displayname');
        }
    }

    public function down(Schema $schema): void
    {
        $engine = $this->connection->getDatabasePlatform()->getName();

        // Address books created without a display name would violate the restored NOT NULL,
        // so fall back to their uri rather than losing the row.
        $this->addSql('UPDATE addressbooks SET displayname = uri WHERE displayname IS NULL');

        if ('mysql' === $engine) {
            $this->addSql('ALTER TABLE addressbooks CHANGE displayname displayname VARCHAR(255) NOT NULL');
        } elseif ('postgresql' === $engine) {
            $this->addSql('ALTER TABLE addressbooks ALTER COLUMN displayname SET NOT NULL');
        } elseif ('sqlite' === $engine) {
            // SQLite refuses to ADD a NOT NULL column without a default, so rebuild the table.
            $this->addSql('CREATE TABLE addressbooks_old (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, principaluri VARCHAR(255) NOT NULL, displayname VARCHAR(255) NOT NULL, uri VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, synctoken VARCHAR(255) NOT NULL, included_in_birthday_calendar INTEGER DEFAULT 0)');
            $this->addSql('INSERT INTO addressbooks_old (id, principaluri, displayname, uri, description, synctoken, included_in_birthday_calendar) SELECT id, principaluri, displayname, uri, description, synctoken, included_in_birthday_calendar FROM addressbooks');
            $this->addSql('DROP TABLE addressbooks');
            $this->addSql('ALTER TABLE addressbooks_old RENAME TO addressbooks');
        }
    }
}
