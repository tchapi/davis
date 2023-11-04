<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * [PostgreSQL] Change BLOB to TEXT (https://github.com/tchapi/davis/issues/110).
 */
final class Version20231001214112 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '[PostgreSQL] Change BLOB to TEXT';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'This migration is specific to \'postgresql\'. Skipping it is fine.');

        $this->addSql("ALTER TABLE calendarobjects ALTER COLUMN calendardata TYPE TEXT USING convert_from(calendardata, 'utf8')");
        $this->addSql("ALTER TABLE cards ALTER COLUMN carddata TYPE TEXT USING convert_from(carddata, 'utf8')");
        $this->addSql("ALTER TABLE schedulingobjects ALTER COLUMN calendardata TYPE TEXT USING convert_from(calendardata, 'utf8')");
    }

    public function down(Schema $schema): void
    {
        $this->skipIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'This migration is specific to \'postgresql\'. Skipping it is fine.');

        $this->addSql("ALTER TABLE calendarobjects ALTER COLUMN calendardata TYPE BYTEA DEFAULT NULL USING convert_from(calendardata, 'utf8')");
        $this->addSql("ALTER TABLE cards ALTER COLUMN carddata TYPE BYTEA DEFAULT NULL USING convert_from(carddata, 'utf8')");
        $this->addSql("ALTER TABLE schedulingobjects ALTER COLUMN calendardata TYPE BYTEA DEFAULT NULL USING convert_from(calendardata, 'utf8')");
    }
}
