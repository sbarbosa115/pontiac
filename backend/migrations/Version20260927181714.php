<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260927181714 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Platform admin: consultants\' limits and features, the platform\'s settings and their change log, the email log.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE outgoing_email (kind VARCHAR(40) NOT NULL, recipient VARCHAR(255) NOT NULL, subject VARCHAR(255) NOT NULL, status VARCHAR(10) NOT NULL, error LONGTEXT DEFAULT NULL, sent_at DATETIME NOT NULL, id BINARY(16) NOT NULL, account_id BINARY(16) DEFAULT NULL, INDEX idx_email_sent_at (sent_at), INDEX idx_email_account_sent_at (account_id, sent_at), INDEX idx_email_status_sent_at (status, sent_at), INDEX IDX_75BE2D149B6B5FBA (account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE platform_settings (platform_name VARCHAR(80) NOT NULL, support_email VARCHAR(180) NOT NULL, sender_name VARCHAR(80) NOT NULL, enabled_templates JSON NOT NULL, reminder_hours JSON NOT NULL, min_notice_hours SMALLINT NOT NULL, booking_window_days SMALLINT NOT NULL, client_cancel_hours SMALLINT NOT NULL, session_buffer_minutes SMALLINT NOT NULL, default_max_published_pages SMALLINT NOT NULL, default_max_assistants SMALLINT NOT NULL, default_storage_mb INT NOT NULL, default_max_file_mb SMALLINT NOT NULL, default_features JSON NOT NULL, terms_text LONGTEXT NOT NULL, default_privacy_text LONGTEXT NOT NULL, reserved_slugs JSON NOT NULL, updated_at DATETIME DEFAULT NULL, id BINARY(16) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE platform_settings_change (changes JSON NOT NULL, fields LONGTEXT NOT NULL, changed_at DATETIME NOT NULL, id BINARY(16) NOT NULL, changed_by_id BINARY(16) NOT NULL, INDEX idx_settings_change_at (changed_at), INDEX IDX_9CFBF573828AD0A0 (changed_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE outgoing_email ADD CONSTRAINT FK_75BE2D149B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id)');
        $this->addSql('ALTER TABLE platform_settings_change ADD CONSTRAINT FK_9CFBF573828AD0A0 FOREIGN KEY (changed_by_id) REFERENCES app_user (id)');
        // Existing consultants keep everything they had: every feature on, and the default limits. Added nullable,
        // filled, then required, so the migration works on a table with rows (no lock beyond a small table's ALTER).
        $this->addSql('ALTER TABLE account ADD features JSON DEFAULT NULL, ADD max_published_pages SMALLINT DEFAULT NULL, ADD max_assistants SMALLINT DEFAULT NULL, ADD storage_mb INT DEFAULT NULL, ADD max_file_mb SMALLINT DEFAULT NULL');
        $this->addSql('UPDATE account SET features = \'["booking","payments","portal","flows"]\', max_published_pages = 10, max_assistants = 3, storage_mb = 1024, max_file_mb = 10');
        $this->addSql('ALTER TABLE account MODIFY features JSON NOT NULL, MODIFY max_published_pages SMALLINT NOT NULL, MODIFY max_assistants SMALLINT NOT NULL, MODIFY storage_mb INT NOT NULL, MODIFY max_file_mb SMALLINT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE outgoing_email DROP FOREIGN KEY FK_75BE2D149B6B5FBA');
        $this->addSql('ALTER TABLE platform_settings_change DROP FOREIGN KEY FK_9CFBF573828AD0A0');
        $this->addSql('DROP TABLE outgoing_email');
        $this->addSql('DROP TABLE platform_settings');
        $this->addSql('DROP TABLE platform_settings_change');
        $this->addSql('ALTER TABLE account DROP features, DROP max_published_pages, DROP max_assistants, DROP storage_mb, DROP max_file_mb');
    }
}
