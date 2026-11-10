<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103155429 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Devis : taux de TVA et d'acompte enregistrés sur chaque devis";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE quote ADD vat_rate NUMERIC(4, 2) NOT NULL, ADD deposit_rate NUMERIC(4, 2) NOT NULL');
        // Les devis déjà enregistrés ont été faits aux taux en vigueur : TVA 20 %, acompte 30 %
        $this->addSql('UPDATE quote SET vat_rate = 20, deposit_rate = 30');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE quote DROP vat_rate, DROP deposit_rate');
    }
}
