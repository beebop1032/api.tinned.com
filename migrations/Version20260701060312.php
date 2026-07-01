<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260701060312 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE invoice_counter (id INT AUTO_INCREMENT NOT NULL, year INT NOT NULL, last_number INT DEFAULT 0 NOT NULL, UNIQUE INDEX UNIQ_33FBA2ABBB827337 (year), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE customer_order ADD invoice_number VARCHAR(40) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3B1CE6A32DA68207 ON customer_order (invoice_number)');
        $this->addSql('ALTER TABLE order_line ADD vat_rate_percent INT DEFAULT 21 NOT NULL');
        $this->addSql('ALTER TABLE product ADD vat_rate_percent INT DEFAULT 21 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE invoice_counter');
        $this->addSql('DROP INDEX UNIQ_3B1CE6A32DA68207 ON customer_order');
        $this->addSql('ALTER TABLE customer_order DROP invoice_number');
        $this->addSql('ALTER TABLE order_line DROP vat_rate_percent');
        $this->addSql('ALTER TABLE product DROP vat_rate_percent');
    }
}
