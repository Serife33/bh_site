<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103204540 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Devis : acompte saisi à la main, vide pour les devis existants";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE quote ADD deposit_amount NUMERIC(12, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE quote DROP deposit_amount');
    }
}
