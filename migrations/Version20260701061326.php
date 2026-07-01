<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260701061326 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE bundle_item (id INT AUTO_INCREMENT NOT NULL, bundle_id INT NOT NULL, variant_id INT NOT NULL, quantity INT DEFAULT 1 NOT NULL, INDEX IDX_236C3EDEF1FAD9D3 (bundle_id), INDEX IDX_236C3EDE3B69A9AF (variant_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE product_bundle (id INT AUTO_INCREMENT NOT NULL, store_box_id INT NOT NULL, name VARCHAR(180) NOT NULL, slug VARCHAR(200) NOT NULL, description LONGTEXT DEFAULT NULL, images JSON NOT NULL, pricing_type VARCHAR(16) DEFAULT \'fixed\' NOT NULL, fixed_price_cents INT DEFAULT 0 NOT NULL, discount_percent INT DEFAULT 0 NOT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, INDEX IDX_C7077359BC00EDA9 (store_box_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE bundle_item ADD CONSTRAINT FK_236C3EDEF1FAD9D3 FOREIGN KEY (bundle_id) REFERENCES product_bundle (id)');
        $this->addSql('ALTER TABLE bundle_item ADD CONSTRAINT FK_236C3EDE3B69A9AF FOREIGN KEY (variant_id) REFERENCES product_variant (id)');
        $this->addSql('ALTER TABLE product_bundle ADD CONSTRAINT FK_C7077359BC00EDA9 FOREIGN KEY (store_box_id) REFERENCES store_box (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bundle_item DROP FOREIGN KEY FK_236C3EDEF1FAD9D3');
        $this->addSql('ALTER TABLE bundle_item DROP FOREIGN KEY FK_236C3EDE3B69A9AF');
        $this->addSql('ALTER TABLE product_bundle DROP FOREIGN KEY FK_C7077359BC00EDA9');
        $this->addSql('DROP TABLE bundle_item');
        $this->addSql('DROP TABLE product_bundle');
    }
}
