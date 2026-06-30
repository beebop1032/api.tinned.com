<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260630094448 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE landing_page ADD slug VARCHAR(180) DEFAULT NULL, CHANGE box_id box_id INT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_landing_slug_locale ON landing_page (slug, locale)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_landing_slug_locale ON landing_page');
        $this->addSql('ALTER TABLE landing_page DROP slug, CHANGE box_id box_id INT NOT NULL');
    }
}
