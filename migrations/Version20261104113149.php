<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261104113149 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Catégories : case « Visible sur le site », cochée pour toutes les catégories existantes';
    }

    public function up(Schema $schema): void
    {
        // DEFAULT 1 : les catégories déjà en ligne restent visibles
        $this->addSql('ALTER TABLE category ADD is_visible TINYINT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category DROP is_visible');
    }
}
