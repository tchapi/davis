<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260720103000 extends AbstractMigration
{
    private const UNIQUE_INDEXES = [
        'uniq_addressbooks_principal_uri' => ['addressbooks', ['principaluri', 'uri'], false],
        'uniq_calendarinstances_principal_uri' => ['calendarinstances', ['principaluri', 'uri'], true],
        'uniq_calendarinstances_calendar_principal' => ['calendarinstances', ['calendarid', 'principaluri'], true],
        'uniq_calendarinstances_calendar_share' => ['calendarinstances', ['calendarid', 'share_href'], true],
        'uniq_calendarobjects_calendar_uri' => ['calendarobjects', ['calendarid', 'uri'], true],
        'uniq_calendarsubscriptions_principal_uri' => ['calendarsubscriptions', ['principaluri', 'uri'], false],
        'uniq_cards_addressbook_uri' => ['cards', ['addressbookid', 'uri'], true],
        'uniq_propertystorage_path_name' => ['propertystorage', ['path', 'name'], false],
        'uniq_schedulingobjects_principal_uri' => ['schedulingobjects', ['principaluri', 'uri'], true],
    ];

    public function getDescription(): string
    {
        return 'Restore DAV uniqueness constraints and add sync query indexes';
    }

    public function up(Schema $schema): void
    {
        foreach (self::UNIQUE_INDEXES as $name => [$table, $columns, $nullable]) {
            $where = $nullable
                ? ' WHERE '.implode(' AND ', array_map(static fn (string $column): string => $column.' IS NOT NULL', $columns))
                : '';
            $columnList = implode(', ', $columns);
            $duplicate = $this->connection->fetchOne(sprintf(
                'SELECT 1 FROM (SELECT 1 FROM %s%s GROUP BY %s HAVING COUNT(*) > 1) duplicate_rows',
                $table,
                $where,
                $columnList,
            ));

            $this->abortIf(false !== $duplicate, sprintf(
                'Cannot create %s: duplicate (%s) values exist in %s. Resolve them and rerun the migration.',
                $name,
                $columnList,
                $table,
            ));

            $this->addSql(sprintf('CREATE UNIQUE INDEX %s ON %s (%s)', $name, $table, $columnList));
        }

        $this->addSql('CREATE INDEX idx_calendarchanges_calendar_sync ON calendarchanges (calendarid, synctoken)');
        $this->addSql('CREATE INDEX idx_addressbookchanges_book_sync ON addressbookchanges (addressbookid, synctoken)');
    }

    public function down(Schema $schema): void
    {
        $engine = $this->connection->getDatabasePlatform()->getName();

        $this->dropIndex('idx_addressbookchanges_book_sync', 'addressbookchanges', $engine);
        $this->dropIndex('idx_calendarchanges_calendar_sync', 'calendarchanges', $engine);

        foreach (array_reverse(self::UNIQUE_INDEXES, true) as $name => [$table]) {
            $this->dropIndex($name, $table, $engine);
        }
    }

    private function dropIndex(string $name, string $table, string $engine): void
    {
        if ('mysql' === $engine) {
            $this->addSql(sprintf('DROP INDEX %s ON %s', $name, $table));

            return;
        }

        $this->addSql(sprintf('DROP INDEX %s', $name));
    }
}
