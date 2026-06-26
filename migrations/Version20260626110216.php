<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260626110216 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'TravelBox devient hub : FK travel_box_id (nullable) sur store_box, business_box, blog_box';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE blog_box ADD travel_box_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE blog_box ADD CONSTRAINT FK_DCCC630FABDC101 FOREIGN KEY (travel_box_id) REFERENCES travel_box (id)');
        $this->addSql('CREATE INDEX IDX_DCCC630FABDC101 ON blog_box (travel_box_id)');
        $this->addSql('ALTER TABLE business_box ADD travel_box_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE business_box ADD CONSTRAINT FK_96DA794AFABDC101 FOREIGN KEY (travel_box_id) REFERENCES travel_box (id)');
        $this->addSql('CREATE INDEX IDX_96DA794AFABDC101 ON business_box (travel_box_id)');
        $this->addSql('ALTER TABLE store_box ADD travel_box_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE store_box ADD CONSTRAINT FK_97D92F3FABDC101 FOREIGN KEY (travel_box_id) REFERENCES travel_box (id)');
        $this->addSql('CREATE INDEX IDX_97D92F3FABDC101 ON store_box (travel_box_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE blog_box DROP FOREIGN KEY FK_DCCC630FABDC101');
        $this->addSql('DROP INDEX IDX_DCCC630FABDC101 ON blog_box');
        $this->addSql('ALTER TABLE blog_box DROP travel_box_id');
        $this->addSql('ALTER TABLE business_box DROP FOREIGN KEY FK_96DA794AFABDC101');
        $this->addSql('DROP INDEX IDX_96DA794AFABDC101 ON business_box');
        $this->addSql('ALTER TABLE business_box DROP travel_box_id');
        $this->addSql('ALTER TABLE store_box DROP FOREIGN KEY FK_97D92F3FABDC101');
        $this->addSql('DROP INDEX IDX_97D92F3FABDC101 ON store_box');
        $this->addSql('ALTER TABLE store_box DROP travel_box_id');
    }
}
