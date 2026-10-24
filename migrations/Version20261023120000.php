<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the app_keys table (admin-defined keys acting on behalf of users)';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof AbstractMySQLPlatform) {
            $this->addSql('CREATE TABLE app_keys (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, app_id VARCHAR(64) NOT NULL, secret_hash VARCHAR(255) NOT NULL, scope JSON NOT NULL COMMENT \'(DC2Type:json)\', revoked_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', last_used_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_app_keys_app_id (app_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        } elseif ($platform instanceof PostgreSQLPlatform) {
            $this->addSql('CREATE SEQUENCE app_keys_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
            $this->addSql("CREATE TABLE app_keys (id INT DEFAULT nextval('app_keys_id_seq') NOT NULL, name VARCHAR(255) NOT NULL, app_id VARCHAR(64) NOT NULL, secret_hash VARCHAR(255) NOT NULL, scope JSON NOT NULL, revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))");
            $this->addSql('CREATE UNIQUE INDEX uniq_app_keys_app_id ON app_keys (app_id)');
            $this->addSql("COMMENT ON COLUMN app_keys.revoked_at IS '(DC2Type:datetime_immutable)'");
            $this->addSql("COMMENT ON COLUMN app_keys.created_at IS '(DC2Type:datetime_immutable)'");
            $this->addSql("COMMENT ON COLUMN app_keys.last_used_at IS '(DC2Type:datetime_immutable)'");
        } elseif ($platform instanceof SqlitePlatform) {
            $this->addSql('CREATE TABLE app_keys (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, app_id VARCHAR(64) NOT NULL, secret_hash VARCHAR(255) NOT NULL, scope CLOB NOT NULL, revoked_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, last_used_at DATETIME DEFAULT NULL)');
            $this->addSql('CREATE UNIQUE INDEX uniq_app_keys_app_id ON app_keys (app_id)');
        }
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        $this->addSql('DROP TABLE app_keys');

        if ($platform instanceof PostgreSQLPlatform) {
            $this->addSql('DROP SEQUENCE app_keys_id_seq');
        }
    }
}
