<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103204926 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Devis : livraison et montage en options, à la place du retrait au showroom';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE quote ADD delivery TINYINT NOT NULL, ADD assembly TINYINT NOT NULL');
        // Les devis existants gardent leur sens : sans retrait, la livraison était comptée (à 0 € : offerte),
        // et le montage seulement quand il avait un prix
        $this->addSql('UPDATE quote SET delivery = NOT pickup, assembly = (NOT pickup AND assembly_fee > 0)');
        $this->addSql('ALTER TABLE quote DROP pickup');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE quote ADD pickup TINYINT NOT NULL');
        $this->addSql('UPDATE quote SET pickup = NOT delivery');
        $this->addSql('ALTER TABLE quote DROP delivery, DROP assembly');
    }
}
