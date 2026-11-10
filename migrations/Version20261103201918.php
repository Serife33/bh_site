<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103201918 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Lignes de devis : variante du produit choisi';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE quote_line ADD variant_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE quote_line ADD CONSTRAINT FK_43F3EB7C3B69A9AF FOREIGN KEY (variant_id) REFERENCES product_variant (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_43F3EB7C3B69A9AF ON quote_line (variant_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE quote_line DROP FOREIGN KEY FK_43F3EB7C3B69A9AF');
        $this->addSql('DROP INDEX IDX_43F3EB7C3B69A9AF ON quote_line');
        $this->addSql('ALTER TABLE quote_line DROP variant_id');
    }
}
