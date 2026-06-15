<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260527100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add configurable delivery methods, tiered delivery prices and backoffice shipping labels.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE delivery_method (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(80) NOT NULL, provider VARCHAR(40) NOT NULL, method VARCHAR(40) NOT NULL, name VARCHAR(120) NOT NULL, description VARCHAR(180) DEFAULT NULL, country_code VARCHAR(2) NOT NULL, delivery_days_min INT NOT NULL, delivery_days_max INT NOT NULL, position INT DEFAULT 0 NOT NULL, recommended TINYINT(1) DEFAULT 0 NOT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, UNIQUE INDEX uniq_delivery_method_country_code (country_code, code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE delivery_price (id INT AUTO_INCREMENT NOT NULL, delivery_method_id INT NOT NULL, order_price_cents INT NOT NULL, price_cents INT NOT NULL, INDEX IDX_DAC3C44B5DED75F5 (delivery_method_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE shipping_label (id INT AUTO_INCREMENT NOT NULL, store_order_id INT NOT NULL, carrier_code VARCHAR(80) NOT NULL, carrier_name VARCHAR(120) NOT NULL, format VARCHAR(20) DEFAULT \'A6\' NOT NULL, copies INT DEFAULT 1 NOT NULL, weight_grams INT DEFAULT 1000 NOT NULL, status VARCHAR(20) NOT NULL, tracking_number VARCHAR(180) DEFAULT NULL, label_url VARCHAR(280) DEFAULT NULL, error_message LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', printed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_E0388D52B3E812C2 (store_order_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE delivery_price ADD CONSTRAINT FK_DAC3C44B5DED75F5 FOREIGN KEY (delivery_method_id) REFERENCES delivery_method (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shipping_label ADD CONSTRAINT FK_E0388D52B3E812C2 FOREIGN KEY (store_order_id) REFERENCES store_order (id)');

        $methods = [
            ['mondial-relay-pickup', 'mondial_relay', 'relay', 'Mondial Relay', 'Point relais proche de l\'adresse', 499, 1, true],
            ['dpd-home', 'dpd', 'at_home', 'DPD domicile', 'Livraison a domicile avec suivi', 799, 2, false],
            ['bpost-locker', 'bpost', 'parcel_locker', 'Bpost distributeur', 'Distributeur de paquets disponible 24/7', 799, 3, false],
        ];
        foreach (['BE', 'FR'] as $countryCode) {
            foreach ($methods as [$code, $provider, $method, $name, $description, $price, $position, $recommended]) {
                $this->addSql(
                    'INSERT INTO delivery_method (code, provider, method, name, description, country_code, delivery_days_min, delivery_days_max, position, recommended, active) VALUES (?, ?, ?, ?, ?, ?, 2, 4, ?, ?, 1)',
                    [$code, $provider, $method, $name, $description, $countryCode, $position, $recommended ? 1 : 0],
                );
                $this->addSql('INSERT INTO delivery_price (delivery_method_id, order_price_cents, price_cents) VALUES (LAST_INSERT_ID(), 0, ?), (LAST_INSERT_ID(), 6900, 0)', [$price]);
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE delivery_price DROP FOREIGN KEY FK_DAC3C44B5DED75F5');
        $this->addSql('ALTER TABLE shipping_label DROP FOREIGN KEY FK_E0388D52B3E812C2');
        $this->addSql('DROP TABLE delivery_price');
        $this->addSql('DROP TABLE delivery_method');
        $this->addSql('DROP TABLE shipping_label');
    }
}
