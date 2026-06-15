<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260527120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tracking URL and pickup point information required to generate carrier labels.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shipping_label ADD tracking_url VARCHAR(280) DEFAULT NULL, ADD pickup_point_id VARCHAR(120) DEFAULT NULL, ADD pickup_point_name VARCHAR(180) DEFAULT NULL, ADD pickup_point_street VARCHAR(180) DEFAULT NULL, ADD pickup_point_postal_code VARCHAR(20) DEFAULT NULL, ADD pickup_point_city VARCHAR(120) DEFAULT NULL, ADD pickup_point_country_code VARCHAR(2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shipping_label DROP tracking_url, DROP pickup_point_id, DROP pickup_point_name, DROP pickup_point_street, DROP pickup_point_postal_code, DROP pickup_point_city, DROP pickup_point_country_code');
    }
}
