<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Index the columns sync-collection reports filter and order on.
 *
 * Adapted from the work of @AnnoyingTechnology in tchapi/davis#276.
 */
final class Version20260910100000 extends AbstractMigration
{
    private const INDEXES = [
        'idx_calendarchanges_calendar_sync' => ['calendarchanges', 'calendarid, synctoken'],
        'idx_addressbookchanges_book_sync' => ['addressbookchanges', 'addressbookid, synctoken'],
    ];

    public function getDescription(): string
    {
        return 'Add the (collection, synctoken) indexes that sabre/dav sync-collection reports rely on';
    }

    public function up(Schema $schema): void
    {
        // Every sync-collection REPORT runs
        //   WHERE synctoken >= ? AND synctoken < ? AND <collection>id = ? ORDER BY synctoken
        // and only the foreign-key column was indexed, which is not selective enough on its
        // own: measured on MariaDB with 150k rows, the planner ignored it and did a full scan
        // with a filesort (150k rows examined, ~14 ms) where the composite index examines 500
        // rows in under 1 ms.
        foreach (self::INDEXES as $name => [$table, $columns]) {
            $this->addSql(sprintf('CREATE INDEX %s ON %s (%s)', $name, $table, $columns));
        }
    }

    public function down(Schema $schema): void
    {
        $isMysql = 'mysql' === $this->connection->getDatabasePlatform()->getName();

        foreach (self::INDEXES as $name => [$table]) {
            $this->addSql($isMysql
                ? sprintf('DROP INDEX %s ON %s', $name, $table)
                : sprintf('DROP INDEX %s', $name));
        }
    }
}
