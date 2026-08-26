<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260826064609 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'User: email_verified_at + email_verify_token + unsubscribe_token, password nullable (comptes lead).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user ADD email_verified_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD email_verify_token VARCHAR(64) DEFAULT NULL, ADD unsubscribe_token VARCHAR(64) DEFAULT NULL, CHANGE password password VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_USER_EMAIL_VERIFY_TOKEN ON user (email_verify_token)');
        $this->addSql('CREATE INDEX IDX_USER_UNSUBSCRIBE_TOKEN ON user (unsubscribe_token)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX IDX_USER_EMAIL_VERIFY_TOKEN ON user');
        $this->addSql('DROP INDEX IDX_USER_UNSUBSCRIBE_TOKEN ON user');
        $this->addSql('ALTER TABLE user DROP email_verified_at, DROP email_verify_token, DROP unsubscribe_token, CHANGE password password VARCHAR(255) NOT NULL');
    }
}
