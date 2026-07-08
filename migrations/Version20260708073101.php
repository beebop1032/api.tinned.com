<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260708073101 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE landing_page ADD product_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE landing_page ADD CONSTRAINT FK_87A7C8994584665A FOREIGN KEY (product_id) REFERENCES product (id)');
        $this->addSql('CREATE INDEX IDX_87A7C8994584665A ON landing_page (product_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_landing_product_locale ON landing_page (product_id, locale)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE landing_page DROP FOREIGN KEY FK_87A7C8994584665A');
        $this->addSql('DROP INDEX IDX_87A7C8994584665A ON landing_page');
        $this->addSql('DROP INDEX uniq_landing_product_locale ON landing_page');
        $this->addSql('ALTER TABLE landing_page DROP product_id');
    }
}
