<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260701062455 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE box_subscription (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, store_box_id INT DEFAULT NULL, variant_id INT NOT NULL, frequency VARCHAR(16) DEFAULT \'monthly\' NOT NULL, status VARCHAR(16) DEFAULT \'active\' NOT NULL, next_renewal_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', mollie_mandate_id VARCHAR(120) DEFAULT NULL, mollie_subscription_id VARCHAR(120) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_6B338844A76ED395 (user_id), INDEX IDX_6B338844BC00EDA9 (store_box_id), INDEX IDX_6B3388443B69A9AF (variant_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE box_subscription ADD CONSTRAINT FK_6B338844A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE box_subscription ADD CONSTRAINT FK_6B338844BC00EDA9 FOREIGN KEY (store_box_id) REFERENCES store_box (id)');
        $this->addSql('ALTER TABLE box_subscription ADD CONSTRAINT FK_6B3388443B69A9AF FOREIGN KEY (variant_id) REFERENCES product_variant (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE box_subscription DROP FOREIGN KEY FK_6B338844A76ED395');
        $this->addSql('ALTER TABLE box_subscription DROP FOREIGN KEY FK_6B338844BC00EDA9');
        $this->addSql('ALTER TABLE box_subscription DROP FOREIGN KEY FK_6B3388443B69A9AF');
        $this->addSql('DROP TABLE box_subscription');
    }
}
