<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add product.hide_price_when_unavailable: hides the price and disables pre-ordering
 * for a not-yet-released product (waitlist-only) when the seller opts in.
 */
final class Version20260715120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add product.hide_price_when_unavailable';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ADD hide_price_when_unavailable TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP hide_price_when_unavailable');
    }
}
