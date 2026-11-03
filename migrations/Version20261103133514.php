<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103133514 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Sections de l'accueil choisies dans l'admin : tables home_section et home_section_product, avec les trois sections d'avant";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE home_section (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(120) NOT NULL, subtitle VARCHAR(255) DEFAULT NULL, position INT NOT NULL, is_visible TINYINT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci`');
        $this->addSql('CREATE TABLE home_section_product (id INT AUTO_INCREMENT NOT NULL, position INT NOT NULL, section_id INT NOT NULL, product_id INT NOT NULL, INDEX IDX_965F68F2D823E37A (section_id), INDEX IDX_965F68F24584665A (product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci`');
        $this->addSql('ALTER TABLE home_section_product ADD CONSTRAINT FK_965F68F2D823E37A FOREIGN KEY (section_id) REFERENCES home_section (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE home_section_product ADD CONSTRAINT FK_965F68F24584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');

        // L'accueil reste identique : les produits que HomeSectionsBuilder choisissait le 3/11/2026, dans l'ordre affiché.
        // [titre, sous-titre, identifiants des produits]
        $sections = [
            ['Nouveautés', 'Nos dernières pièces arrivées', [25, 231, 441, 284, 24, 151, 442, 285]],
            ['Promotions', 'Nos offres du moment', [237, 113, 6, 114, 7, 115, 18, 116]],
            ['Disponible immédiatement', 'En stock, livraison rapide', [30, 102, 43, 122]],
        ];
        foreach ($sections as $index => [$title, $subtitle, $ids]) {
            $id = $index + 1;   // table neuve : la section n° 1 est aussi la première de l'accueil
            $this->addSql('INSERT INTO home_section (id, title, subtitle, position, is_visible) VALUES (?, ?, ?, ?, 1)', [$id, $title, $subtitle, $id]);

            // Un produit absent de la base est ignoré ; FIELD() donne à chacun son rang dans la liste
            $list = implode(', ', $ids);
            $this->addSql("INSERT INTO home_section_product (section_id, product_id, position) SELECT ?, id, FIELD(id, $list) FROM product WHERE id IN ($list)", [$id]);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE home_section_product DROP FOREIGN KEY FK_965F68F2D823E37A');
        $this->addSql('ALTER TABLE home_section_product DROP FOREIGN KEY FK_965F68F24584665A');
        $this->addSql('DROP TABLE home_section');
        $this->addSql('DROP TABLE home_section_product');
    }
}
