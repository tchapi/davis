<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260720104000 extends AbstractMigration
{
    private const FOREIGN_KEYS = [
        'cards' => [
            'FK_4C258FD8B26C2E9' => ['addressbookid', 'addressbooks'],
        ],
        'addressbookchanges' => [
            'FK_EB122CD58B26C2E9' => ['addressbookid', 'addressbooks'],
        ],
        'calendarobjects' => [
            'FK_E14F332CB8CB7204' => ['calendarid', 'calendars'],
        ],
        'calendarinstances' => [
            'FK_51856561B8CB7204' => ['calendarid', 'calendars'],
        ],
        'calendarchanges' => [
            'FK_737547E2B8CB7204' => ['calendarid', 'calendars'],
        ],
        'groupmembers' => [
            'FK_6F15EDAC474870EE' => ['principal_id', 'principals'],
            'FK_6F15EDAC7597D3FE' => ['member_id', 'principals'],
        ],
    ];

    public function getDescription(): string
    {
        return 'Enforce foreign keys consistently across supported databases';
    }

    public function up(Schema $schema): void
    {
        foreach (self::FOREIGN_KEYS as $table => $foreignKeys) {
            foreach ($foreignKeys as $name => [$column, $parentTable]) {
                $orphan = $this->connection->fetchOne(sprintf(
                    'SELECT 1 FROM %s child LEFT JOIN %s parent ON parent.id = child.%s WHERE parent.id IS NULL LIMIT 1',
                    $table,
                    $parentTable,
                    $column,
                ));

                $this->abortIf(false !== $orphan, sprintf(
                    'Cannot create %s: %s.%s contains values missing from %s.id. Resolve orphaned rows and rerun the migration.',
                    $name,
                    $table,
                    $column,
                    $parentTable,
                ));
            }
        }

        $this->changeForeignKeys(true);
    }

    public function down(Schema $schema): void
    {
        $this->changeForeignKeys(false);
    }

    private function changeForeignKeys(bool $enable): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $currentSchema = $schemaManager->introspectSchema();
        $comparator = $schemaManager->createComparator();
        $platform = $this->connection->getDatabasePlatform();
        $remove = !$enable && 'sqlite' === $platform->getName();

        foreach (self::FOREIGN_KEYS as $tableName => $foreignKeys) {
            $currentTable = $currentSchema->getTable($tableName);
            $targetTable = clone $currentTable;

            foreach ($foreignKeys as $name => [$column, $parentTable]) {
                if ($targetTable->hasForeignKey($name)) {
                    $targetTable->removeForeignKey($name);
                }

                if (!$remove) {
                    $targetTable->addForeignKeyConstraint(
                        $parentTable,
                        [$column],
                        ['id'],
                        $enable ? ['onDelete' => 'CASCADE'] : [],
                        $name,
                    );
                }
            }

            $diff = $comparator->compareTables($currentTable, $targetTable);
            foreach ($platform->getAlterTableSQL($diff) as $sql) {
                $this->addSql($sql);
            }
        }
    }
}
