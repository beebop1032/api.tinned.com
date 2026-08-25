<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260824103036 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute subscription.notified_at (anti-double-envoi des emails de lancement/retour en stock).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscription ADD notified_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscription DROP notified_at');
    }
}
