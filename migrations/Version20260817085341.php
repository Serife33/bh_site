<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260817085341 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE fabric ADD reference VARCHAR(120) DEFAULT NULL, ADD supplier VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE fabric_color DROP FOREIGN KEY `FK_EF1915F7ADA1FB5`');
        $this->addSql('DROP INDEX IDX_EF1915F7ADA1FB5 ON fabric_color');
        $this->addSql('DROP INDEX uniq_fabric_color ON fabric_color');
        $this->addSql('ALTER TABLE fabric_color ADD name VARCHAR(120) NOT NULL, ADD hex VARCHAR(7) NOT NULL, DROP color_id');
        $this->addSql('CREATE UNIQUE INDEX uniq_fabric_colorname ON fabric_color (fabric_id, name)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE fabric DROP reference, DROP supplier');
        $this->addSql('DROP INDEX uniq_fabric_colorname ON fabric_color');
        $this->addSql('ALTER TABLE fabric_color ADD color_id INT NOT NULL, DROP name, DROP hex');
        $this->addSql('ALTER TABLE fabric_color ADD CONSTRAINT `FK_EF1915F7ADA1FB5` FOREIGN KEY (color_id) REFERENCES color (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_EF1915F7ADA1FB5 ON fabric_color (color_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_fabric_color ON fabric_color (fabric_id, color_id)');
    }
}
