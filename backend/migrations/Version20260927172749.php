<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260927172749 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Consultants\' accounts, the people who sign in (staff and clients), and the Messenger queue table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE account (name VARCHAR(180) NOT NULL, slug VARCHAR(60) NOT NULL, country VARCHAR(2) NOT NULL, currency VARCHAR(3) NOT NULL, locale VARCHAR(16) NOT NULL, timezone VARCHAR(64) NOT NULL, active TINYINT NOT NULL, created_at DATETIME NOT NULL, id BINARY(16) NOT NULL, UNIQUE INDEX UNIQ_7D3656A4989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE app_user (email VARCHAR(180) NOT NULL, login_scope VARCHAR(36) NOT NULL, full_name VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) DEFAULT NULL, active TINYINT NOT NULL, invitation_token_hash VARCHAR(64) DEFAULT NULL, invitation_expires_at DATETIME DEFAULT NULL, ui_theme VARCHAR(10) DEFAULT NULL, created_at DATETIME NOT NULL, last_sign_in_at DATETIME DEFAULT NULL, id BINARY(16) NOT NULL, account_id BINARY(16) DEFAULT NULL, UNIQUE INDEX UNIQ_88BDF3E9298D7A97 (invitation_token_hash), INDEX idx_user_account (account_id), UNIQUE INDEX uniq_user_email_scope (email, login_scope), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE app_user ADD CONSTRAINT FK_88BDF3E99B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP FOREIGN KEY FK_88BDF3E99B6B5FBA');
        $this->addSql('DROP TABLE account');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
