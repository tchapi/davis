<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add missing groupmembers table, and delegates.
 */
final class Version20191113170650 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add missing groupmembers table for proxy/read and proxy/write, and add an info in the principals table to separate main principals from proxy.';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'This migration is specific to \'mysql\'. Skipping it is fine.');

        $this->addSql('CREATE TABLE groupmembers (principal_id INT NOT NULL, member_id INT NOT NULL, INDEX IDX_6F15EDAC474870EE (principal_id), INDEX IDX_6F15EDAC7597D3FE (member_id), PRIMARY KEY(principal_id, member_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE groupmembers ADD CONSTRAINT FK_6F15EDAC474870EE FOREIGN KEY (principal_id) REFERENCES principals (id)');
        $this->addSql('ALTER TABLE groupmembers ADD CONSTRAINT FK_6F15EDAC7597D3FE FOREIGN KEY (member_id) REFERENCES principals (id)');
        $this->addSql('ALTER TABLE principals ADD is_main TINYINT(1) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->skipIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'This migration is specific to \'mysql\'. Skipping it is fine.');

        $this->addSql('DROP TABLE groupmembers');
        $this->addSql('ALTER TABLE principals DROP is_main');
    }
}
