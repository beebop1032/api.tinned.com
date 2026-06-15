<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260521084032 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the initial tinned database schema for the new API.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE address (
              id INT AUTO_INCREMENT NOT NULL,
              user_id INT DEFAULT NULL,
              first_name VARCHAR(120) NOT NULL,
              last_name VARCHAR(120) NOT NULL,
              street VARCHAR(180) NOT NULL,
              postal_code VARCHAR(20) NOT NULL,
              city VARCHAR(120) NOT NULL,
              country_code VARCHAR(2) NOT NULL,
              phone VARCHAR(40) DEFAULT NULL,
              INDEX IDX_D4E6F81A76ED395 (user_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE article (
              id INT AUTO_INCREMENT NOT NULL,
              blog_box_id INT NOT NULL,
              title VARCHAR(180) NOT NULL,
              slug VARCHAR(200) NOT NULL,
              excerpt VARCHAR(280) DEFAULT NULL,
              body LONGTEXT NOT NULL,
              image_path VARCHAR(280) DEFAULT NULL,
              published TINYINT(1) DEFAULT 0 NOT NULL,
              published_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
              INDEX IDX_23A0E664D58DEFE (blog_box_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE blog_box (
              id INT NOT NULL,
              business_box_id INT DEFAULT NULL,
              store_box_id INT DEFAULT NULL,
              INDEX IDX_DCCC63083B734C5 (business_box_id),
              INDEX IDX_DCCC630BC00EDA9 (store_box_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE box (
              id INT AUTO_INCREMENT NOT NULL,
              name VARCHAR(160) NOT NULL,
              slug VARCHAR(180) NOT NULL,
              tagline VARCHAR(280) DEFAULT NULL,
              description LONGTEXT DEFAULT NULL,
              logo_path VARCHAR(280) DEFAULT NULL,
              cover_path VARCHAR(280) DEFAULT NULL,
              active TINYINT(1) DEFAULT 1 NOT NULL,
              created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              box_type VARCHAR(255) NOT NULL,
              UNIQUE INDEX UNIQ_8A9483A989D9B62 (slug),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE business_box (
              id INT NOT NULL,
              company_name VARCHAR(180) DEFAULT NULL,
              website VARCHAR(180) DEFAULT NULL,
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE cart (
              id INT AUTO_INCREMENT NOT NULL,
              user_id INT DEFAULT NULL,
              token VARCHAR(80) NOT NULL,
              selected_store_slugs JSON NOT NULL,
              selected_carrier_by_store JSON NOT NULL,
              updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              UNIQUE INDEX UNIQ_BA388B75F37A13B (token),
              INDEX IDX_BA388B7A76ED395 (user_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE cart_item (
              id INT AUTO_INCREMENT NOT NULL,
              cart_id INT NOT NULL,
              variant_id INT NOT NULL,
              quantity INT NOT NULL,
              INDEX IDX_F0FE25271AD5CDBF (cart_id),
              INDEX IDX_F0FE25273B69A9AF (variant_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE customer_order (
              id INT AUTO_INCREMENT NOT NULL,
              user_id INT DEFAULT NULL,
              shipping_address_id INT DEFAULT NULL,
              reference VARCHAR(40) NOT NULL,
              subtotal_cents INT NOT NULL,
              shipping_cents INT NOT NULL,
              status VARCHAR(40) NOT NULL,
              payment_status VARCHAR(40) DEFAULT 'open' NOT NULL,
              mollie_payment_id VARCHAR(120) DEFAULT NULL,
              total_cents INT NOT NULL,
              currency VARCHAR(3) DEFAULT 'EUR' NOT NULL,
              created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              UNIQUE INDEX UNIQ_3B1CE6A3AEA34913 (reference),
              INDEX IDX_3B1CE6A3A76ED395 (user_id),
              INDEX IDX_3B1CE6A34D4CFF2B (shipping_address_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE landing_page (
              id INT AUTO_INCREMENT NOT NULL,
              box_id INT DEFAULT NULL,
              slug VARCHAR(120) NOT NULL,
              locale VARCHAR(5) DEFAULT 'fr' NOT NULL,
              title VARCHAR(180) NOT NULL,
              meta_description VARCHAR(280) DEFAULT NULL,
              blocks JSON NOT NULL,
              INDEX IDX_87A7C899D8177B3F (box_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE order_line (
              id INT AUTO_INCREMENT NOT NULL,
              customer_order_id INT NOT NULL,
              store_order_id INT DEFAULT NULL,
              variant_id INT DEFAULT NULL,
              store_box_id INT DEFAULT NULL,
              store_name_snapshot VARCHAR(180) NOT NULL,
              product_name_snapshot VARCHAR(180) NOT NULL,
              attributes_snapshot JSON NOT NULL,
              unit_price_cents_snapshot INT NOT NULL,
              quantity INT NOT NULL,
              INDEX IDX_9CE58EE1A15A2E17 (customer_order_id),
              INDEX IDX_9CE58EE1B3E812C2 (store_order_id),
              INDEX IDX_9CE58EE13B69A9AF (variant_id),
              INDEX IDX_9CE58EE1BC00EDA9 (store_box_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE product (
              id INT AUTO_INCREMENT NOT NULL,
              store_box_id INT NOT NULL,
              name VARCHAR(180) NOT NULL,
              slug VARCHAR(200) NOT NULL,
              description LONGTEXT DEFAULT NULL,
              base_price_cents INT NOT NULL,
              currency VARCHAR(3) DEFAULT 'EUR' NOT NULL,
              active TINYINT(1) DEFAULT 1 NOT NULL,
              images JSON NOT NULL,
              created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              INDEX IDX_D34A04ADBC00EDA9 (store_box_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE product_attribute (
              id INT AUTO_INCREMENT NOT NULL,
              code VARCHAR(80) NOT NULL,
              name VARCHAR(120) NOT NULL,
              type VARCHAR(20) NOT NULL,
              UNIQUE INDEX UNIQ_94DA597677153098 (code),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE product_attribute_value (
              id INT AUTO_INCREMENT NOT NULL,
              attribute_id INT NOT NULL,
              label VARCHAR(120) NOT NULL,
              value VARCHAR(120) NOT NULL,
              hex_color VARCHAR(7) DEFAULT NULL,
              position INT DEFAULT 0 NOT NULL,
              INDEX IDX_CCC4BE1FB6E62EFA (attribute_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE product_variant (
              id INT AUTO_INCREMENT NOT NULL,
              product_id INT NOT NULL,
              sku VARCHAR(100) NOT NULL,
              price_cents INT NOT NULL,
              stock INT NOT NULL,
              active TINYINT(1) DEFAULT 1 NOT NULL,
              images JSON NOT NULL,
              UNIQUE INDEX UNIQ_209AA41DF9038C4 (sku),
              INDEX IDX_209AA41D4584665A (product_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE product_variant_attribute_value (
              product_variant_id INT NOT NULL,
              product_attribute_value_id INT NOT NULL,
              INDEX IDX_A44FC90FA80EF684 (product_variant_id),
              INDEX IDX_A44FC90F9774A42E (product_attribute_value_id),
              PRIMARY KEY(
                product_variant_id, product_attribute_value_id
              )
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE static_page (
              id INT AUTO_INCREMENT NOT NULL,
              slug VARCHAR(120) NOT NULL,
              locale VARCHAR(5) DEFAULT 'fr' NOT NULL,
              title VARCHAR(180) NOT NULL,
              meta_description VARCHAR(280) DEFAULT NULL,
              sections JSON NOT NULL,
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE store_box (
              id INT NOT NULL,
              business_box_id INT DEFAULT NULL,
              INDEX IDX_97D92F383B734C5 (business_box_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE store_order (
              id INT AUTO_INCREMENT NOT NULL,
              customer_order_id INT NOT NULL,
              store_box_id INT DEFAULT NULL,
              store_name_snapshot VARCHAR(180) NOT NULL,
              status VARCHAR(40) NOT NULL,
              carrier_code VARCHAR(80) DEFAULT NULL,
              carrier_name_snapshot VARCHAR(120) DEFAULT NULL,
              delivery_mode VARCHAR(40) DEFAULT NULL,
              subtotal_cents INT NOT NULL,
              shipping_cents INT NOT NULL,
              total_cents INT NOT NULL,
              currency VARCHAR(3) DEFAULT 'EUR' NOT NULL,
              created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              INDEX IDX_8917C581A15A2E17 (customer_order_id),
              INDEX IDX_8917C581BC00EDA9 (store_box_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user (
              id INT AUTO_INCREMENT NOT NULL,
              email VARCHAR(180) NOT NULL,
              roles JSON NOT NULL,
              password VARCHAR(255) NOT NULL,
              oauth_provider VARCHAR(50) DEFAULT NULL,
              oauth_id VARCHAR(255) DEFAULT NULL,
              active TINYINT(1) DEFAULT 1 NOT NULL,
              UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email),
              UNIQUE INDEX UNIQ_IDENTIFIER_OAUTH_ACCOUNT (oauth_provider, oauth_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              address
            ADD
              CONSTRAINT FK_D4E6F81A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              article
            ADD
              CONSTRAINT FK_23A0E664D58DEFE FOREIGN KEY (blog_box_id) REFERENCES blog_box (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              blog_box
            ADD
              CONSTRAINT FK_DCCC63083B734C5 FOREIGN KEY (business_box_id) REFERENCES business_box (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              blog_box
            ADD
              CONSTRAINT FK_DCCC630BC00EDA9 FOREIGN KEY (store_box_id) REFERENCES store_box (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              blog_box
            ADD
              CONSTRAINT FK_DCCC630BF396750 FOREIGN KEY (id) REFERENCES box (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              business_box
            ADD
              CONSTRAINT FK_96DA794ABF396750 FOREIGN KEY (id) REFERENCES box (id) ON DELETE CASCADE
        SQL);
        $this->addSql('ALTER TABLE cart ADD CONSTRAINT FK_BA388B7A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              cart_item
            ADD
              CONSTRAINT FK_F0FE25271AD5CDBF FOREIGN KEY (cart_id) REFERENCES cart (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              cart_item
            ADD
              CONSTRAINT FK_F0FE25273B69A9AF FOREIGN KEY (variant_id) REFERENCES product_variant (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              customer_order
            ADD
              CONSTRAINT FK_3B1CE6A3A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              customer_order
            ADD
              CONSTRAINT FK_3B1CE6A34D4CFF2B FOREIGN KEY (shipping_address_id) REFERENCES address (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              landing_page
            ADD
              CONSTRAINT FK_87A7C899D8177B3F FOREIGN KEY (box_id) REFERENCES box (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              order_line
            ADD
              CONSTRAINT FK_9CE58EE1A15A2E17 FOREIGN KEY (customer_order_id) REFERENCES customer_order (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              order_line
            ADD
              CONSTRAINT FK_9CE58EE1B3E812C2 FOREIGN KEY (store_order_id) REFERENCES store_order (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              order_line
            ADD
              CONSTRAINT FK_9CE58EE13B69A9AF FOREIGN KEY (variant_id) REFERENCES product_variant (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              order_line
            ADD
              CONSTRAINT FK_9CE58EE1BC00EDA9 FOREIGN KEY (store_box_id) REFERENCES store_box (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              product
            ADD
              CONSTRAINT FK_D34A04ADBC00EDA9 FOREIGN KEY (store_box_id) REFERENCES store_box (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              product_attribute_value
            ADD
              CONSTRAINT FK_CCC4BE1FB6E62EFA FOREIGN KEY (attribute_id) REFERENCES product_attribute (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              product_variant
            ADD
              CONSTRAINT FK_209AA41D4584665A FOREIGN KEY (product_id) REFERENCES product (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              product_variant_attribute_value
            ADD
              CONSTRAINT FK_A44FC90FA80EF684 FOREIGN KEY (product_variant_id) REFERENCES product_variant (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              product_variant_attribute_value
            ADD
              CONSTRAINT FK_A44FC90F9774A42E FOREIGN KEY (product_attribute_value_id) REFERENCES product_attribute_value (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              store_box
            ADD
              CONSTRAINT FK_97D92F383B734C5 FOREIGN KEY (business_box_id) REFERENCES business_box (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              store_box
            ADD
              CONSTRAINT FK_97D92F3BF396750 FOREIGN KEY (id) REFERENCES box (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              store_order
            ADD
              CONSTRAINT FK_8917C581A15A2E17 FOREIGN KEY (customer_order_id) REFERENCES customer_order (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              store_order
            ADD
              CONSTRAINT FK_8917C581BC00EDA9 FOREIGN KEY (store_box_id) REFERENCES store_box (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE address DROP FOREIGN KEY FK_D4E6F81A76ED395');
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E664D58DEFE');
        $this->addSql('ALTER TABLE blog_box DROP FOREIGN KEY FK_DCCC63083B734C5');
        $this->addSql('ALTER TABLE blog_box DROP FOREIGN KEY FK_DCCC630BC00EDA9');
        $this->addSql('ALTER TABLE blog_box DROP FOREIGN KEY FK_DCCC630BF396750');
        $this->addSql('ALTER TABLE business_box DROP FOREIGN KEY FK_96DA794ABF396750');
        $this->addSql('ALTER TABLE cart DROP FOREIGN KEY FK_BA388B7A76ED395');
        $this->addSql('ALTER TABLE cart_item DROP FOREIGN KEY FK_F0FE25271AD5CDBF');
        $this->addSql('ALTER TABLE cart_item DROP FOREIGN KEY FK_F0FE25273B69A9AF');
        $this->addSql('ALTER TABLE customer_order DROP FOREIGN KEY FK_3B1CE6A3A76ED395');
        $this->addSql('ALTER TABLE customer_order DROP FOREIGN KEY FK_3B1CE6A34D4CFF2B');
        $this->addSql('ALTER TABLE landing_page DROP FOREIGN KEY FK_87A7C899D8177B3F');
        $this->addSql('ALTER TABLE order_line DROP FOREIGN KEY FK_9CE58EE1A15A2E17');
        $this->addSql('ALTER TABLE order_line DROP FOREIGN KEY FK_9CE58EE1B3E812C2');
        $this->addSql('ALTER TABLE order_line DROP FOREIGN KEY FK_9CE58EE13B69A9AF');
        $this->addSql('ALTER TABLE order_line DROP FOREIGN KEY FK_9CE58EE1BC00EDA9');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04ADBC00EDA9');
        $this->addSql('ALTER TABLE product_attribute_value DROP FOREIGN KEY FK_CCC4BE1FB6E62EFA');
        $this->addSql('ALTER TABLE product_variant DROP FOREIGN KEY FK_209AA41D4584665A');
        $this->addSql('ALTER TABLE product_variant_attribute_value DROP FOREIGN KEY FK_A44FC90FA80EF684');
        $this->addSql('ALTER TABLE product_variant_attribute_value DROP FOREIGN KEY FK_A44FC90F9774A42E');
        $this->addSql('ALTER TABLE store_box DROP FOREIGN KEY FK_97D92F383B734C5');
        $this->addSql('ALTER TABLE store_box DROP FOREIGN KEY FK_97D92F3BF396750');
        $this->addSql('ALTER TABLE store_order DROP FOREIGN KEY FK_8917C581A15A2E17');
        $this->addSql('ALTER TABLE store_order DROP FOREIGN KEY FK_8917C581BC00EDA9');
        $this->addSql('DROP TABLE address');
        $this->addSql('DROP TABLE article');
        $this->addSql('DROP TABLE blog_box');
        $this->addSql('DROP TABLE box');
        $this->addSql('DROP TABLE business_box');
        $this->addSql('DROP TABLE cart');
        $this->addSql('DROP TABLE cart_item');
        $this->addSql('DROP TABLE customer_order');
        $this->addSql('DROP TABLE landing_page');
        $this->addSql('DROP TABLE order_line');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE product_attribute');
        $this->addSql('DROP TABLE product_attribute_value');
        $this->addSql('DROP TABLE product_variant');
        $this->addSql('DROP TABLE product_variant_attribute_value');
        $this->addSql('DROP TABLE static_page');
        $this->addSql('DROP TABLE store_box');
        $this->addSql('DROP TABLE store_order');
        $this->addSql('DROP TABLE user');
    }
}
