<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103133743 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Devis : tables customer, quote, quote_line et document_counter';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE customer (id INT AUTO_INCREMENT NOT NULL, last_name VARCHAR(100) NOT NULL, first_name VARCHAR(100) DEFAULT NULL, phone VARCHAR(20) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci`');
        $this->addSql('CREATE TABLE document_counter (prefix VARCHAR(11) NOT NULL, last_number INT NOT NULL, PRIMARY KEY (prefix)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci`');
        $this->addSql('CREATE TABLE quote (id INT AUTO_INCREMENT NOT NULL, number VARCHAR(20) NOT NULL, status VARCHAR(255) NOT NULL, pickup TINYINT NOT NULL, delivery_fee NUMERIC(10, 2) NOT NULL, assembly_fee NUMERIC(10, 2) NOT NULL, total NUMERIC(12, 2) NOT NULL, lead_time VARCHAR(255) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, valid_until DATE NOT NULL, accepted_at DATETIME DEFAULT NULL, discount_value NUMERIC(10, 2) NOT NULL, discount_type VARCHAR(255) NOT NULL, customer_id INT NOT NULL, seller_id INT NOT NULL, UNIQUE INDEX UNIQ_6B71CBF496901F54 (number), INDEX IDX_6B71CBF49395C3F3 (customer_id), INDEX IDX_6B71CBF48DE820D9 (seller_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci`');
        $this->addSql('CREATE TABLE quote_line (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(255) NOT NULL, finish VARCHAR(255) DEFAULT NULL, details LONGTEXT DEFAULT NULL, quantity INT NOT NULL, unit_price NUMERIC(10, 2) NOT NULL, custom_made TINYINT NOT NULL, discount_value NUMERIC(10, 2) NOT NULL, discount_type VARCHAR(255) NOT NULL, quote_id INT NOT NULL, product_id INT DEFAULT NULL, INDEX IDX_43F3EB7CDB805178 (quote_id), INDEX IDX_43F3EB7C4584665A (product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci`');
        $this->addSql('ALTER TABLE quote ADD CONSTRAINT FK_6B71CBF49395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id)');
        $this->addSql('ALTER TABLE quote ADD CONSTRAINT FK_6B71CBF48DE820D9 FOREIGN KEY (seller_id) REFERENCES admin_user (id)');
        $this->addSql('ALTER TABLE quote_line ADD CONSTRAINT FK_43F3EB7CDB805178 FOREIGN KEY (quote_id) REFERENCES quote (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quote_line ADD CONSTRAINT FK_43F3EB7C4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE quote DROP FOREIGN KEY FK_6B71CBF49395C3F3');
        $this->addSql('ALTER TABLE quote DROP FOREIGN KEY FK_6B71CBF48DE820D9');
        $this->addSql('ALTER TABLE quote_line DROP FOREIGN KEY FK_43F3EB7CDB805178');
        $this->addSql('ALTER TABLE quote_line DROP FOREIGN KEY FK_43F3EB7C4584665A');
        $this->addSql('DROP TABLE customer');
        $this->addSql('DROP TABLE document_counter');
        $this->addSql('DROP TABLE quote');
        $this->addSql('DROP TABLE quote_line');
    }
}
