<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260826080853 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Retrait de subscription.confirm_token (ancien double opt-in anonyme abandonné).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX IDX_SUBSCRIPTION_CONFIRM_TOKEN ON subscription');
        $this->addSql('ALTER TABLE subscription DROP confirm_token');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE subscription ADD confirm_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_SUBSCRIPTION_CONFIRM_TOKEN ON subscription (confirm_token)');
    }
}
