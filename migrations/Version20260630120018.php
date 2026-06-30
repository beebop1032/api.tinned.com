<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260630120018 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE subscription (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, box_id INT DEFAULT NULL, product_id INT DEFAULT NULL, email VARCHAR(180) NOT NULL, target_type VARCHAR(12) NOT NULL, consent_tinned TINYINT(1) DEFAULT 0 NOT NULL, status VARCHAR(12) DEFAULT \'pending\' NOT NULL, confirm_token VARCHAR(64) DEFAULT NULL, confirmed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', locale VARCHAR(5) DEFAULT \'fr\' NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_A3C664D3A76ED395 (user_id), INDEX IDX_A3C664D3D8177B3F (box_id), INDEX IDX_A3C664D34584665A (product_id), INDEX IDX_SUBSCRIPTION_CONFIRM_TOKEN (confirm_token), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT FK_A3C664D3A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT FK_A3C664D3D8177B3F FOREIGN KEY (box_id) REFERENCES box (id)');
        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT FK_A3C664D34584665A FOREIGN KEY (product_id) REFERENCES product (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE subscription DROP FOREIGN KEY FK_A3C664D3A76ED395');
        $this->addSql('ALTER TABLE subscription DROP FOREIGN KEY FK_A3C664D3D8177B3F');
        $this->addSql('ALTER TABLE subscription DROP FOREIGN KEY FK_A3C664D34584665A');
        $this->addSql('DROP TABLE subscription');
    }
}
