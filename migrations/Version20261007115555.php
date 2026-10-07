<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261007115555 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Photo principale : la position remplace le drapeau is_main';
    }

    public function up(Schema $schema): void
    {
        // Avant ce lot, la photo principale était marquée par is_main et pouvait occuper
        // n'importe quelle position. Désormais c'est la première de la galerie : on la fait
        // passer devant AVANT de supprimer la colonne, sinon les vignettes du catalogue changent.
        $this->addSql('UPDATE media SET position = 0 WHERE is_main = 1');
        $this->addSql('ALTER TABLE media DROP is_main');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE media ADD is_main TINYINT NOT NULL');
    }
}
