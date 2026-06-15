<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store customer profile details and registration consent choices.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD first_name VARCHAR(120) DEFAULT NULL, ADD last_name VARCHAR(120) DEFAULT NULL, ADD phone VARCHAR(40) DEFAULT NULL, ADD terms_accepted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD marketing_consent TINYINT(1) DEFAULT 0 NOT NULL, ADD marketing_consent_updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP first_name, DROP last_name, DROP phone, DROP terms_accepted_at, DROP marketing_consent, DROP marketing_consent_updated_at');
    }
}
