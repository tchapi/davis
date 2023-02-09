<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * PostgreSQL - Add missing defaults to IDs (to use sequences)
 */
final class Version20230209142217 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '[PostgreSQL] Add missing defaults to IDs (to use sequences)';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'This migration is specific to \'postgresql\'. Skipping it is fine.');

        $this->addSql('ALTER TABLE addressbooks ALTER COLUMN id SET DEFAULT nextval(\'addressbooks_id_seq\');');
        $this->addSql('ALTER TABLE calendars ALTER COLUMN id SET DEFAULT nextval(\'calendars_id_seq\');');
        $this->addSql('ALTER TABLE cards ALTER COLUMN id SET DEFAULT nextval(\'cards_id_seq\');');
        $this->addSql('ALTER TABLE calendarsubscriptions ALTER COLUMN id SET DEFAULT nextval(\'calendarsubscriptions_id_seq\');');
        $this->addSql('ALTER TABLE schedulingobjects ALTER COLUMN id SET DEFAULT nextval(\'schedulingobjects_id_seq\');');
        $this->addSql('ALTER TABLE locks ALTER COLUMN id SET DEFAULT nextval(\'locks_id_seq\');');
        $this->addSql('ALTER TABLE calendarinstances ALTER COLUMN id SET DEFAULT nextval(\'calendarinstances_id_seq\');');
        $this->addSql('ALTER TABLE addressbookchanges ALTER COLUMN id SET DEFAULT nextval(\'addressbookchanges_id_seq\');');
        $this->addSql('ALTER TABLE principals ALTER COLUMN id SET DEFAULT nextval(\'principals_id_seq\');');
        $this->addSql('ALTER TABLE calendarchanges ALTER COLUMN id SET DEFAULT nextval(\'calendarchanges_id_seq\');');
        $this->addSql('ALTER TABLE users ALTER COLUMN id SET DEFAULT nextval(\'users_id_seq\');');
        $this->addSql('ALTER TABLE calendarobjects ALTER COLUMN id SET DEFAULT nextval(\'calendarobjects_id_seq\');');
        $this->addSql('ALTER TABLE propertystorage ALTER COLUMN id SET DEFAULT nextval(\'propertystorage_id_seq\');');
        $this->addSql('ALTER TABLE addressbooks ALTER COLUMN synctoken TYPE integer USING synctoken::integer;')
        $this->addSql('ALTER TABLE calendars ALTER COLUMN synctoken TYPE integer USING synctoken::integer;')
    }

    public function down(Schema $schema): void
    {
        $this->skipIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'This migration is specific to \'postgresql\'. Skipping it is fine.');

        $this->addSql('ALTER TABLE addressbooks ALTER COLUMN id DROP DEFAULT;');
        $this->addSql('ALTER TABLE calendars ALTER COLUMN id DROP DEFAULT;');
        $this->addSql('ALTER TABLE cards ALTER COLUMN id DROP DEFAULT;');
        $this->addSql('ALTER TABLE calendarsubscriptions ALTER COLUMN id DROP DEFAULT;');
        $this->addSql('ALTER TABLE schedulingobjects ALTER COLUMN id DROP DEFAULT;');
        $this->addSql('ALTER TABLE locks ALTER COLUMN id DROP DEFAULT;');
        $this->addSql('ALTER TABLE calendarinstances ALTER COLUMN id DROP DEFAULT;');
        $this->addSql('ALTER TABLE addressbookchanges ALTER COLUMN id DROP DEFAULT;');
        $this->addSql('ALTER TABLE principals ALTER COLUMN id DROP DEFAULT;');
        $this->addSql('ALTER TABLE calendarchanges ALTER COLUMN id DROP DEFAULT;');
        $this->addSql('ALTER TABLE users ALTER COLUMN id DROP DEFAULT;');
        $this->addSql('ALTER TABLE calendarobjects ALTER COLUMN id DROP DEFAULT;');
        $this->addSql('ALTER TABLE propertystorage ALTER COLUMN id DROP DEFAULT;');
        $this->addSql('ALTER TABLE addressbooks ALTER COLUMN synctoken TYPE varchar(255);')
        $this->addSql('ALTER TABLE calendars ALTER COLUMN synctoken TYPE varchar(255);')
    }
}

