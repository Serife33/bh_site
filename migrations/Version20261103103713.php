<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103103713 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Slugs des produits uniques : correction des doublons, puis index UNIQUE';
    }

    public function up(Schema $schema): void
    {
        // L'index échouerait sur les doublons : dans chaque groupe, le plus petit id garde
        // son adresse, les autres reçoivent leur id en suffixe (lit-coffre-lina-140x190-90).
        $this->addSql('UPDATE product p
            JOIN (SELECT slug, MIN(id) AS keep_id FROM product GROUP BY slug HAVING COUNT(*) > 1) d ON d.slug = p.slug
            SET p.slug = CONCAT(p.slug, \'-\', p.id)
            WHERE p.id <> d.keep_id');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D34A04AD989D9B62 ON product (slug)');
    }

    public function down(Schema $schema): void
    {
        // Les adresses renommées restent : rétablir les doublons rendrait de nouveau une fiche inaccessible.
        $this->addSql('DROP INDEX UNIQ_D34A04AD989D9B62 ON product');
    }
}
