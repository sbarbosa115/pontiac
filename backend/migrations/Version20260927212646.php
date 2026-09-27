<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Client portal: contacts' files, and logins' contact and password-reset token. Additive. */
final class Version20260927212646 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Client files; app_user contact, password reset token and expiry (additive)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE client_file (name VARCHAR(200) NOT NULL, content_type VARCHAR(100) NOT NULL, size_bytes INT NOT NULL, shared TINYINT NOT NULL, active TINYINT NOT NULL, created_at DATETIME NOT NULL, id BINARY(16) NOT NULL, contact_id BINARY(16) NOT NULL, uploaded_by_id BINARY(16) NOT NULL, account_id BINARY(16) NOT NULL, INDEX idx_client_file_account_contact (account_id, contact_id), INDEX IDX_5D03F572E7A1254A (contact_id), INDEX IDX_5D03F572A2B28FE8 (uploaded_by_id), INDEX IDX_5D03F5729B6B5FBA (account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE client_file ADD CONSTRAINT FK_5D03F572E7A1254A FOREIGN KEY (contact_id) REFERENCES contact (id)');
        $this->addSql('ALTER TABLE client_file ADD CONSTRAINT FK_5D03F572A2B28FE8 FOREIGN KEY (uploaded_by_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE client_file ADD CONSTRAINT FK_5D03F5729B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id)');
        $this->addSql('ALTER TABLE app_user ADD password_reset_token_hash VARCHAR(64) DEFAULT NULL, ADD password_reset_expires_at DATETIME DEFAULT NULL, ADD contact_id BINARY(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE app_user ADD CONSTRAINT FK_88BDF3E9E7A1254A FOREIGN KEY (contact_id) REFERENCES contact (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E9FBE5498 ON app_user (password_reset_token_hash)');
        $this->addSql('CREATE INDEX IDX_88BDF3E9E7A1254A ON app_user (contact_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client_file DROP FOREIGN KEY FK_5D03F572E7A1254A');
        $this->addSql('ALTER TABLE client_file DROP FOREIGN KEY FK_5D03F572A2B28FE8');
        $this->addSql('ALTER TABLE client_file DROP FOREIGN KEY FK_5D03F5729B6B5FBA');
        $this->addSql('DROP TABLE client_file');
        $this->addSql('ALTER TABLE app_user DROP FOREIGN KEY FK_88BDF3E9E7A1254A');
        $this->addSql('DROP INDEX UNIQ_88BDF3E9FBE5498 ON app_user');
        $this->addSql('DROP INDEX IDX_88BDF3E9E7A1254A ON app_user');
        $this->addSql('ALTER TABLE app_user DROP password_reset_token_hash, DROP password_reset_expires_at, DROP contact_id');
    }
}
