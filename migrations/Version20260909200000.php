<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add the unique index on propertystorage(path, name) that sabre/dav assumes.
 */
final class Version20260909200000 extends AbstractMigration
{
    private const INDEX_NAME = 'propertystorage_path_name';

    public function getDescription(): string
    {
        return 'Deduplicate propertystorage and add the UNIQUE(path, name) index sabre/dav relies on to store dead properties';
    }

    public function up(Schema $schema): void
    {
        // \Sabre\DAV\PropertyStorage\Backend\PDO::propPatch() stores a property with
        // `INSERT ... ON CONFLICT (path, name) DO UPDATE` on PostgreSQL and `REPLACE INTO`
        // elsewhere. Both need a unique key on (path, name):
        //  - PostgreSQL rejects every PROPPATCH with SQLSTATE 42P10 (HTTP 500),
        //  - MySQL and SQLite silently append a new row on each PROPPATCH instead of
        //    replacing, so the table grows without bound and reads pick an arbitrary row.
        //
        // Collapse any duplicates a previous install accumulated, keeping the most recently
        // written row (the highest id) for each property. The nested SELECT is what MySQL
        // needs to read from the table it is deleting from.
        $this->addSql('DELETE FROM propertystorage WHERE id NOT IN (SELECT id FROM (SELECT MAX(id) AS id FROM propertystorage GROUP BY path, name) AS keep)');

        $this->addSql(sprintf('CREATE UNIQUE INDEX %s ON propertystorage (path, name)', self::INDEX_NAME));
    }

    public function down(Schema $schema): void
    {
        // NB: the rows removed by up() cannot be restored; they were stale duplicates.
        if ('mysql' === $this->connection->getDatabasePlatform()->getName()) {
            $this->addSql(sprintf('DROP INDEX %s ON propertystorage', self::INDEX_NAME));
        } else {
            $this->addSql(sprintf('DROP INDEX %s', self::INDEX_NAME));
        }
    }
}
