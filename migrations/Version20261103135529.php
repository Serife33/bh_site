<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103135529 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Factures d'acompte : table invoice";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE invoice (id INT AUTO_INCREMENT NOT NULL, number VARCHAR(20) DEFAULT NULL, issued_at DATETIME DEFAULT NULL, customer_name VARCHAR(255) NOT NULL, customer_address VARCHAR(255) DEFAULT NULL, quote_id INT NOT NULL, UNIQUE INDEX UNIQ_9065174496901F54 (number), INDEX IDX_90651744DB805178 (quote_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci`');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_90651744DB805178 FOREIGN KEY (quote_id) REFERENCES quote (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_90651744DB805178');
        $this->addSql('DROP TABLE invoice');
    }
}
