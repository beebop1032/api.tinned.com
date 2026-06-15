<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260603144459 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE travel_box (id INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE trip (id INT AUTO_INCREMENT NOT NULL, travel_box_id INT NOT NULL, title VARCHAR(180) NOT NULL, slug VARCHAR(200) NOT NULL, locale VARCHAR(5) DEFAULT \'fr\' NOT NULL, excerpt VARCHAR(280) DEFAULT NULL, body LONGTEXT NOT NULL, image_path VARCHAR(280) DEFAULT NULL, published TINYINT(1) DEFAULT 0 NOT NULL, published_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_7656F53BFABDC101 (travel_box_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE travel_box ADD CONSTRAINT FK_31F9698ABF396750 FOREIGN KEY (id) REFERENCES box (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE trip ADD CONSTRAINT FK_7656F53BFABDC101 FOREIGN KEY (travel_box_id) REFERENCES travel_box (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE travel_box DROP FOREIGN KEY FK_31F9698ABF396750');
        $this->addSql('ALTER TABLE trip DROP FOREIGN KEY FK_7656F53BFABDC101');
        $this->addSql('DROP TABLE travel_box');
        $this->addSql('DROP TABLE trip');
    }
}
