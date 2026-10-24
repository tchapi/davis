<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261024120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the app_key_usages table (which users each app key has acted as)';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof AbstractMySQLPlatform) {
            $this->addSql('CREATE TABLE app_key_usages (id INT AUTO_INCREMENT NOT NULL, app_key_id INT NOT NULL, login VARCHAR(255) NOT NULL, first_used_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', last_used_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_16F91F49E81738B3 (app_key_id), UNIQUE INDEX uniq_app_key_usages_key_login (app_key_id, login), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
            $this->addSql('ALTER TABLE app_key_usages ADD CONSTRAINT FK_16F91F49E81738B3 FOREIGN KEY (app_key_id) REFERENCES app_keys (id) ON DELETE CASCADE');
        } elseif ($platform instanceof PostgreSQLPlatform) {
            $this->addSql('CREATE SEQUENCE app_key_usages_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
            $this->addSql("CREATE TABLE app_key_usages (id INT DEFAULT nextval('app_key_usages_id_seq') NOT NULL, app_key_id INT NOT NULL, login VARCHAR(255) NOT NULL, first_used_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_used_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))");
            $this->addSql('CREATE INDEX IDX_16F91F49E81738B3 ON app_key_usages (app_key_id)');
            $this->addSql('CREATE UNIQUE INDEX uniq_app_key_usages_key_login ON app_key_usages (app_key_id, login)');
            $this->addSql("COMMENT ON COLUMN app_key_usages.first_used_at IS '(DC2Type:datetime_immutable)'");
            $this->addSql("COMMENT ON COLUMN app_key_usages.last_used_at IS '(DC2Type:datetime_immutable)'");
            $this->addSql('ALTER TABLE app_key_usages ADD CONSTRAINT FK_16F91F49E81738B3 FOREIGN KEY (app_key_id) REFERENCES app_keys (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        } elseif ($platform instanceof SqlitePlatform) {
            $this->addSql('CREATE TABLE app_key_usages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, app_key_id INTEGER NOT NULL, login VARCHAR(255) NOT NULL, first_used_at DATETIME NOT NULL, last_used_at DATETIME NOT NULL, CONSTRAINT FK_16F91F49E81738B3 FOREIGN KEY (app_key_id) REFERENCES app_keys (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
            $this->addSql('CREATE INDEX IDX_16F91F49E81738B3 ON app_key_usages (app_key_id)');
            $this->addSql('CREATE UNIQUE INDEX uniq_app_key_usages_key_login ON app_key_usages (app_key_id, login)');
        }
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        $this->addSql('DROP TABLE app_key_usages');

        if ($platform instanceof PostgreSQLPlatform) {
            $this->addSql('DROP SEQUENCE app_key_usages_id_seq');
        }
    }
}
