<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Store DAV sync tokens as integers.
 *
 * Adapted from the work of @AnnoyingTechnology in tchapi/davis#277.
 */
final class Version20260909100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store DAV sync tokens as integers so that sync-collection range queries compare them numerically';
    }

    public function up(Schema $schema): void
    {
        $engine = $this->connection->getDatabasePlatform()->getName();

        // sabre/dav compares and orders sync tokens numerically:
        //   WHERE synctoken >= ? AND synctoken < ? ... ORDER BY synctoken
        // Stored as text, '10' sorts before '9', so a client syncing across a decimal-width
        // boundary is told the collection advanced but is handed none of the changes.
        if ('mysql' === $engine) {
            $this->addSql('ALTER TABLE addressbooks CHANGE synctoken synctoken INT DEFAULT 1 NOT NULL');
            $this->addSql('ALTER TABLE calendars CHANGE synctoken synctoken INT DEFAULT 1 NOT NULL');
            $this->addSql('ALTER TABLE addressbookchanges CHANGE synctoken synctoken INT DEFAULT 1 NOT NULL');
        } elseif ('postgresql' === $engine) {
            // addressbooks and calendars were already converted by Version20230209142217;
            // only addressbookchanges is still text here.
            $this->addSql('ALTER TABLE addressbookchanges ALTER COLUMN synctoken TYPE INT USING synctoken::integer');
            foreach (['addressbooks', 'calendars', 'addressbookchanges'] as $table) {
                $this->addSql(sprintf('ALTER TABLE %s ALTER COLUMN synctoken SET DEFAULT 1', $table));
            }
        } elseif ('sqlite' === $engine) {
            // A VARCHAR column has TEXT affinity in SQLite, so the comparison is textual there
            // too. SQLite cannot alter a column in place: add the replacement, copy, swap, drop.
            foreach (['addressbooks', 'calendars', 'addressbookchanges'] as $table) {
                $this->replaceSyncTokenColumn($table, 'INTEGER', 'INTEGER', '1');
            }
        }
    }

    public function down(Schema $schema): void
    {
        $engine = $this->connection->getDatabasePlatform()->getName();

        if ('mysql' === $engine) {
            $this->addSql('ALTER TABLE addressbooks CHANGE synctoken synctoken VARCHAR(255) NOT NULL');
            $this->addSql('ALTER TABLE calendars CHANGE synctoken synctoken VARCHAR(255) NOT NULL');
            $this->addSql('ALTER TABLE addressbookchanges CHANGE synctoken synctoken VARCHAR(255) NOT NULL');
        } elseif ('postgresql' === $engine) {
            // Only addressbookchanges goes back to text: addressbooks and calendars were
            // already integers before this migration, and reverting them would reintroduce
            // the error Version20230209142217 fixed (synctoken + 1 on a text column).
            foreach (['addressbooks', 'calendars', 'addressbookchanges'] as $table) {
                $this->addSql(sprintf('ALTER TABLE %s ALTER COLUMN synctoken DROP DEFAULT', $table));
            }
            $this->addSql('ALTER TABLE addressbookchanges ALTER COLUMN synctoken TYPE VARCHAR(255) USING synctoken::varchar');
        } elseif ('sqlite' === $engine) {
            // NB: SQLite refuses to ADD a NOT NULL column without a default, so the restored
            // columns keep a harmless DEFAULT '1' that the original schema did not have.
            foreach (['addressbooks', 'calendars', 'addressbookchanges'] as $table) {
                $this->replaceSyncTokenColumn($table, 'VARCHAR(255)', 'TEXT', "'1'");
            }
        }
    }

    private function replaceSyncTokenColumn(string $table, string $type, string $cast, string $default): void
    {
        $this->addSql(sprintf('ALTER TABLE %s ADD COLUMN new_synctoken %s DEFAULT %s NOT NULL', $table, $type, $default));
        $this->addSql(sprintf('UPDATE %s SET new_synctoken = CAST(synctoken AS %s)', $table, $cast));
        $this->addSql(sprintf('ALTER TABLE %s RENAME COLUMN synctoken TO old_synctoken', $table));
        $this->addSql(sprintf('ALTER TABLE %s RENAME COLUMN new_synctoken TO synctoken', $table));
        $this->addSql(sprintf('ALTER TABLE %s DROP COLUMN old_synctoken', $table));
    }
}
