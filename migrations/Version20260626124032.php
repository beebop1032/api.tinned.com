<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260626124032 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Hiérarchie box: parent Business requis sur store_box, FK parent self sur travel/business, FK travel_box.business_box';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE business_box ADD parent_business_box_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE business_box ADD CONSTRAINT FK_96DA794A147B3039 FOREIGN KEY (parent_business_box_id) REFERENCES business_box (id)');
        $this->addSql('CREATE INDEX IDX_96DA794A147B3039 ON business_box (parent_business_box_id)');
        $this->addSql('ALTER TABLE store_box DROP FOREIGN KEY FK_97D92F3FABDC101');
        $this->addSql('DROP INDEX IDX_97D92F3FABDC101 ON store_box');
        $this->addSql('UPDATE blog_box SET store_box_id = NULL WHERE business_box_id IS NOT NULL AND store_box_id IS NOT NULL');
        $this->addSql('ALTER TABLE store_box DROP travel_box_id, CHANGE business_box_id business_box_id INT NOT NULL');
        $this->addSql('ALTER TABLE travel_box ADD parent_travel_box_id INT DEFAULT NULL, ADD business_box_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE travel_box ADD CONSTRAINT FK_31F9698A263BA293 FOREIGN KEY (parent_travel_box_id) REFERENCES travel_box (id)');
        $this->addSql('ALTER TABLE travel_box ADD CONSTRAINT FK_31F9698A83B734C5 FOREIGN KEY (business_box_id) REFERENCES business_box (id)');
        $this->addSql('CREATE INDEX IDX_31F9698A263BA293 ON travel_box (parent_travel_box_id)');
        $this->addSql('CREATE INDEX IDX_31F9698A83B734C5 ON travel_box (business_box_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE business_box DROP FOREIGN KEY FK_96DA794A147B3039');
        $this->addSql('DROP INDEX IDX_96DA794A147B3039 ON business_box');
        $this->addSql('ALTER TABLE business_box DROP parent_business_box_id');
        $this->addSql('ALTER TABLE store_box ADD travel_box_id INT DEFAULT NULL, CHANGE business_box_id business_box_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE store_box ADD CONSTRAINT FK_97D92F3FABDC101 FOREIGN KEY (travel_box_id) REFERENCES travel_box (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_97D92F3FABDC101 ON store_box (travel_box_id)');
        $this->addSql('ALTER TABLE travel_box DROP FOREIGN KEY FK_31F9698A263BA293');
        $this->addSql('ALTER TABLE travel_box DROP FOREIGN KEY FK_31F9698A83B734C5');
        $this->addSql('DROP INDEX IDX_31F9698A263BA293 ON travel_box');
        $this->addSql('DROP INDEX IDX_31F9698A83B734C5 ON travel_box');
        $this->addSql('ALTER TABLE travel_box DROP parent_travel_box_id, DROP business_box_id');
    }
}
