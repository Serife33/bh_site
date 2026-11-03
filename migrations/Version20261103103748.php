<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261103103748 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE product_variant (id INT AUTO_INCREMENT NOT NULL, size VARCHAR(30) DEFAULT NULL, initial_price NUMERIC(10, 2) NOT NULL, actual_price NUMERIC(10, 2) NOT NULL, stock INT DEFAULT 0 NOT NULL, position INT NOT NULL, legacy_slug VARCHAR(180) DEFAULT NULL, product_id INT NOT NULL, fabric_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_209AA41DB72D207A (legacy_slug), INDEX IDX_209AA41D4584665A (product_id), INDEX IDX_209AA41DAB43EC50 (fabric_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE product_variant ADD CONSTRAINT FK_209AA41D4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_variant ADD CONSTRAINT FK_209AA41DAB43EC50 FOREIGN KEY (fabric_id) REFERENCES fabric (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product_variant DROP FOREIGN KEY FK_209AA41D4584665A');
        $this->addSql('ALTER TABLE product_variant DROP FOREIGN KEY FK_209AA41DAB43EC50');
        $this->addSql('DROP TABLE product_variant');
    }
}
