<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260701062039 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE payout_ledger_entry (id INT AUTO_INCREMENT NOT NULL, store_order_id INT NOT NULL, store_box_id INT NOT NULL, store_reference VARCHAR(40) NOT NULL, gross_cents INT NOT NULL, commission_cents INT NOT NULL, net_cents INT NOT NULL, commission_rate_percent INT NOT NULL, status VARCHAR(16) DEFAULT \'pending\' NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', paid_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_EFE6E0C0B3E812C2 (store_order_id), INDEX IDX_EFE6E0C0BC00EDA9 (store_box_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE payout_ledger_entry ADD CONSTRAINT FK_EFE6E0C0B3E812C2 FOREIGN KEY (store_order_id) REFERENCES store_order (id)');
        $this->addSql('ALTER TABLE payout_ledger_entry ADD CONSTRAINT FK_EFE6E0C0BC00EDA9 FOREIGN KEY (store_box_id) REFERENCES store_box (id)');
        $this->addSql('ALTER TABLE store_box ADD commission_rate_percent INT DEFAULT 10 NOT NULL, ADD vat_number VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE payout_ledger_entry DROP FOREIGN KEY FK_EFE6E0C0B3E812C2');
        $this->addSql('ALTER TABLE payout_ledger_entry DROP FOREIGN KEY FK_EFE6E0C0BC00EDA9');
        $this->addSql('DROP TABLE payout_ledger_entry');
        $this->addSql('ALTER TABLE store_box DROP commission_rate_percent, DROP vat_number');
    }
}
