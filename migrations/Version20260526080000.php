<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add expiring password reset tokens to users.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD password_reset_token_hash VARCHAR(64) DEFAULT NULL, ADD password_reset_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE INDEX IDX_USER_PASSWORD_RESET_TOKEN ON user (password_reset_token_hash)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_USER_PASSWORD_RESET_TOKEN ON user');
        $this->addSql('ALTER TABLE user DROP password_reset_token_hash, DROP password_reset_expires_at');
    }
}
