<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260626092841 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index uniques anti-doublons sur les slugs (par box parente + locale)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE UNIQUE INDEX uniq_article_box_slug ON article (blog_box_id, slug)');
        $this->addSql('CREATE UNIQUE INDEX uniq_landing_box_slug_locale ON landing_page (box_id, slug, locale)');
        $this->addSql('CREATE UNIQUE INDEX uniq_product_box_slug ON product (store_box_id, slug)');
        $this->addSql('CREATE UNIQUE INDEX uniq_static_slug_locale ON static_page (slug, locale)');
        $this->addSql('CREATE UNIQUE INDEX uniq_trip_box_slug_locale ON trip (travel_box_id, slug, locale)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_article_box_slug ON article');
        $this->addSql('DROP INDEX uniq_landing_box_slug_locale ON landing_page');
        $this->addSql('DROP INDEX uniq_product_box_slug ON product');
        $this->addSql('DROP INDEX uniq_static_slug_locale ON static_page');
        $this->addSql('DROP INDEX uniq_trip_box_slug_locale ON trip');
    }
}
