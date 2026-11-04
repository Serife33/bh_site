<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103131017 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Bandeau géré dans l'admin : table announcement, avec les messages qui étaient écrits dans le gabarit";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE announcement (id INT AUTO_INCREMENT NOT NULL, text VARCHAR(120) NOT NULL, link VARCHAR(255) DEFAULT NULL, is_promo TINYINT NOT NULL, start_date DATE DEFAULT NULL, end_date DATE DEFAULT NULL, position INT NOT NULL, is_visible TINYINT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci`');

        // Le bandeau reste identique : l'offre du festival avec ses dates, puis les quatre messages permanents.
        // [texte, promo, début, fin, position]
        $messages = [
            ['-10 % sur tout le magasin du 6 au 8 novembre', 1, '2026-11-06', '2026-11-08', 1],
            ['Livraison partout en France', 0, null, null, 1],
            ['Paiement en 2, 3 ou 4 fois sans frais', 0, null, null, 2],
            ['Showroom : 12 bis rue Suffren, 33300 Bordeaux', 0, null, null, 3],
            ['Tissu, couleur et dimension au choix', 0, null, null, 4],
        ];
        foreach ($messages as $message) {
            $this->addSql('INSERT INTO announcement (text, is_promo, start_date, end_date, position, is_visible) VALUES (?, ?, ?, ?, ?, 1)', $message);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE announcement');
    }
}
