<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Payments: Wompi settings, payments, session notes, and enrollments' payment link, outcome and completion. Additive. */
final class Version20260927202806 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Wompi settings, payments, session notes; enrollment payment token, outcome, completed at (additive)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE payment (reference VARCHAR(40) NOT NULL, amount NUMERIC(15, 2) NOT NULL, currency VARCHAR(3) NOT NULL, status VARCHAR(10) NOT NULL, method VARCHAR(40) DEFAULT NULL, wompi_transaction_id VARCHAR(64) DEFAULT NULL, last_event JSON DEFAULT NULL, `manual` TINYINT NOT NULL, note LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, paid_at DATETIME DEFAULT NULL, id BINARY(16) NOT NULL, enrollment_id BINARY(16) NOT NULL, contact_id BINARY(16) NOT NULL, recorded_by_id BINARY(16) DEFAULT NULL, account_id BINARY(16) NOT NULL, UNIQUE INDEX UNIQ_6D28840DAEA34913 (reference), INDEX idx_payment_account_created (account_id, created_at), INDEX idx_payment_account_status (account_id, status), INDEX IDX_6D28840D8F7DB25B (enrollment_id), INDEX IDX_6D28840DE7A1254A (contact_id), INDEX IDX_6D28840DD05A957B (recorded_by_id), INDEX IDX_6D28840D9B6B5FBA (account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE session_note (body LONGTEXT NOT NULL, visibility VARCHAR(10) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, id BINARY(16) NOT NULL, session_id BINARY(16) NOT NULL, author_id BINARY(16) NOT NULL, account_id BINARY(16) NOT NULL, INDEX idx_session_note_account_session (account_id, session_id), INDEX IDX_9CCFA3E613FECDF (session_id), INDEX IDX_9CCFA3EF675F31B (author_id), INDEX IDX_9CCFA3E9B6B5FBA (account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE wompi_settings (public_key VARCHAR(120) NOT NULL, private_key LONGTEXT NOT NULL, events_secret LONGTEXT NOT NULL, integrity_secret LONGTEXT NOT NULL, endings JSON NOT NULL, updated_at DATETIME NOT NULL, id BINARY(16) NOT NULL, account_id BINARY(16) NOT NULL, UNIQUE INDEX uniq_wompi_settings_account (account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D8F7DB25B FOREIGN KEY (enrollment_id) REFERENCES enrollment (id)');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840DE7A1254A FOREIGN KEY (contact_id) REFERENCES contact (id)');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840DD05A957B FOREIGN KEY (recorded_by_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D9B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id)');
        $this->addSql('ALTER TABLE session_note ADD CONSTRAINT FK_9CCFA3E613FECDF FOREIGN KEY (session_id) REFERENCES session (id)');
        $this->addSql('ALTER TABLE session_note ADD CONSTRAINT FK_9CCFA3EF675F31B FOREIGN KEY (author_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE session_note ADD CONSTRAINT FK_9CCFA3E9B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id)');
        $this->addSql('ALTER TABLE wompi_settings ADD CONSTRAINT FK_3F3B8E8D9B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id)');
        $this->addSql('ALTER TABLE enrollment ADD payment_token VARCHAR(43) DEFAULT NULL, ADD outcome VARCHAR(10) DEFAULT NULL, ADD completed_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DBDCD7E187E9789 ON enrollment (payment_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D8F7DB25B');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840DE7A1254A');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840DD05A957B');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D9B6B5FBA');
        $this->addSql('ALTER TABLE session_note DROP FOREIGN KEY FK_9CCFA3E613FECDF');
        $this->addSql('ALTER TABLE session_note DROP FOREIGN KEY FK_9CCFA3EF675F31B');
        $this->addSql('ALTER TABLE session_note DROP FOREIGN KEY FK_9CCFA3E9B6B5FBA');
        $this->addSql('ALTER TABLE wompi_settings DROP FOREIGN KEY FK_3F3B8E8D9B6B5FBA');
        $this->addSql('DROP TABLE payment');
        $this->addSql('DROP TABLE session_note');
        $this->addSql('DROP TABLE wompi_settings');
        $this->addSql('DROP INDEX UNIQ_DBDCD7E187E9789 ON enrollment');
        $this->addSql('ALTER TABLE enrollment DROP payment_token, DROP outcome, DROP completed_at');
    }
}
