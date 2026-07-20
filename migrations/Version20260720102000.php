<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260720102000 extends AbstractMigration
{
    private const ADDRESSBOOK_SYNC_INDEX = 'idx_addressbookchanges_book_sync';

    public function getDescription(): string
    {
        return 'Store DAV sync tokens as integers so range queries use numeric ordering';
    }

    public function up(Schema $schema): void
    {
        $engine = $this->connection->getDatabasePlatform()->getName();

        if ('mysql' === $engine) {
            $this->addSql('ALTER TABLE addressbooks MODIFY synctoken INT DEFAULT 1 NOT NULL');
            $this->addSql('ALTER TABLE calendars MODIFY synctoken INT DEFAULT 1 NOT NULL');
            $this->addSql('ALTER TABLE addressbookchanges MODIFY synctoken INT DEFAULT 1 NOT NULL');

            return;
        }

        if ('postgresql' === $engine) {
            foreach (['addressbooks', 'calendars', 'addressbookchanges'] as $table) {
                $this->addSql(sprintf('ALTER TABLE %s ALTER COLUMN synctoken TYPE INT USING synctoken::integer', $table));
                $this->addSql(sprintf('ALTER TABLE %s ALTER COLUMN synctoken SET DEFAULT 1', $table));
            }

            return;
        }

        if ('sqlite' === $engine) {
            $restoreSyncIndex = $this->hasAddressBookSyncIndex();
            if ($restoreSyncIndex) {
                $this->addSql('DROP INDEX '.self::ADDRESSBOOK_SYNC_INDEX);
            }

            foreach (['addressbooks', 'calendars', 'addressbookchanges'] as $table) {
                $this->replaceSqliteSyncToken($table, 'INTEGER', 'INTEGER', '1');
            }

            if ($restoreSyncIndex) {
                $this->addSql('CREATE INDEX '.self::ADDRESSBOOK_SYNC_INDEX.' ON addressbookchanges (addressbookid, synctoken)');
            }
        }
    }

    public function down(Schema $schema): void
    {
        $engine = $this->connection->getDatabasePlatform()->getName();

        if ('mysql' === $engine) {
            $this->addSql('ALTER TABLE addressbooks MODIFY synctoken VARCHAR(255) NOT NULL');
            $this->addSql('ALTER TABLE calendars MODIFY synctoken VARCHAR(255) NOT NULL');
            $this->addSql('ALTER TABLE addressbookchanges MODIFY synctoken VARCHAR(255) NOT NULL');

            return;
        }

        if ('postgresql' === $engine) {
            foreach (['addressbooks', 'calendars', 'addressbookchanges'] as $table) {
                $this->addSql(sprintf('ALTER TABLE %s ALTER COLUMN synctoken DROP DEFAULT', $table));
                $this->addSql(sprintf('ALTER TABLE %s ALTER COLUMN synctoken TYPE VARCHAR(255) USING synctoken::varchar', $table));
            }

            return;
        }

        if ('sqlite' === $engine) {
            $restoreSyncIndex = $this->hasAddressBookSyncIndex();
            if ($restoreSyncIndex) {
                $this->addSql('DROP INDEX '.self::ADDRESSBOOK_SYNC_INDEX);
            }

            foreach (['addressbooks', 'calendars', 'addressbookchanges'] as $table) {
                $this->replaceSqliteSyncToken($table, 'VARCHAR(255)', 'TEXT', "'1'");
            }

            if ($restoreSyncIndex) {
                $this->addSql('CREATE INDEX '.self::ADDRESSBOOK_SYNC_INDEX.' ON addressbookchanges (addressbookid, synctoken)');
            }
        }
    }

    private function replaceSqliteSyncToken(string $table, string $type, string $cast, string $default): void
    {
        $this->addSql(sprintf('ALTER TABLE %s ADD COLUMN new_synctoken %s DEFAULT %s NOT NULL', $table, $type, $default));
        $this->addSql(sprintf('UPDATE %s SET new_synctoken = CAST(synctoken AS %s)', $table, $cast));
        $this->addSql(sprintf('ALTER TABLE %s RENAME COLUMN synctoken TO old_synctoken', $table));
        $this->addSql(sprintf('ALTER TABLE %s RENAME COLUMN new_synctoken TO synctoken', $table));
        $this->addSql(sprintf('ALTER TABLE %s DROP COLUMN old_synctoken', $table));
    }

    private function hasAddressBookSyncIndex(): bool
    {
        $indexes = array_change_key_case(
            $this->connection->createSchemaManager()->listTableIndexes('addressbookchanges'),
            CASE_LOWER,
        );

        return isset($indexes[self::ADDRESSBOOK_SYNC_INDEX]);
    }
}
