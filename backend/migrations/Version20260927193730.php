<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Booking: plans, enrollments, sessions, their reminders and each consultant's availability. New tables only. */
final class Version20260927193730 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Plans, enrollments, sessions, session reminders and availability (new tables only)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE availability (weekly_rules JSON NOT NULL, exceptions JSON NOT NULL, buffer_minutes SMALLINT NOT NULL, min_notice_hours SMALLINT NOT NULL, booking_window_days SMALLINT NOT NULL, client_cancel_hours SMALLINT NOT NULL, reminder_hours JSON NOT NULL, meeting_link VARCHAR(500) NOT NULL, id BINARY(16) NOT NULL, account_id BINARY(16) NOT NULL, UNIQUE INDEX uniq_availability_account (account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE enrollment (plan_name VARCHAR(120) NOT NULL, price NUMERIC(15, 2) NOT NULL, currency VARCHAR(3) NOT NULL, sessions_included SMALLINT NOT NULL, duration_minutes SMALLINT NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, id BINARY(16) NOT NULL, contact_id BINARY(16) NOT NULL, plan_id BINARY(16) NOT NULL, source_page_id BINARY(16) DEFAULT NULL, account_id BINARY(16) NOT NULL, INDEX idx_enrollment_account_contact (account_id, contact_id), INDEX IDX_DBDCD7E1E7A1254A (contact_id), INDEX IDX_DBDCD7E1E899029B (plan_id), INDEX IDX_DBDCD7E14599DB8C (source_page_id), INDEX IDX_DBDCD7E19B6B5FBA (account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE plan (name VARCHAR(120) NOT NULL, description LONGTEXT NOT NULL, price NUMERIC(15, 2) NOT NULL, currency VARCHAR(3) NOT NULL, sessions SMALLINT NOT NULL, duration_minutes SMALLINT NOT NULL, active TINYINT NOT NULL, id BINARY(16) NOT NULL, account_id BINARY(16) NOT NULL, INDEX idx_plan_account_name (account_id, name), INDEX IDX_DD5A5B7D9B6B5FBA (account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE session (starts_at DATETIME NOT NULL, ends_at DATETIME NOT NULL, status VARCHAR(12) NOT NULL, meeting_link VARCHAR(500) NOT NULL, cancel_reason LONGTEXT DEFAULT NULL, manage_token_hash VARCHAR(64) NOT NULL, booked_by VARCHAR(10) NOT NULL, created_at DATETIME NOT NULL, scheduled_at DATETIME NOT NULL, id BINARY(16) NOT NULL, enrollment_id BINARY(16) NOT NULL, contact_id BINARY(16) NOT NULL, account_id BINARY(16) NOT NULL, UNIQUE INDEX UNIQ_D044D5D4F903FB2E (manage_token_hash), INDEX idx_session_account_starts (account_id, starts_at), INDEX idx_session_account_status_starts (account_id, status, starts_at), INDEX IDX_D044D5D48F7DB25B (enrollment_id), INDEX IDX_D044D5D4E7A1254A (contact_id), INDEX IDX_D044D5D49B6B5FBA (account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE session_reminder (hours INT NOT NULL, sent_at DATETIME NOT NULL, id BINARY(16) NOT NULL, session_id BINARY(16) NOT NULL, account_id BINARY(16) NOT NULL, UNIQUE INDEX uniq_reminder_session_hours (session_id, hours), INDEX IDX_CB9B2F28613FECDF (session_id), INDEX IDX_CB9B2F289B6B5FBA (account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE availability ADD CONSTRAINT FK_3FB7A2BF9B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id)');
        $this->addSql('ALTER TABLE enrollment ADD CONSTRAINT FK_DBDCD7E1E7A1254A FOREIGN KEY (contact_id) REFERENCES contact (id)');
        $this->addSql('ALTER TABLE enrollment ADD CONSTRAINT FK_DBDCD7E1E899029B FOREIGN KEY (plan_id) REFERENCES plan (id)');
        $this->addSql('ALTER TABLE enrollment ADD CONSTRAINT FK_DBDCD7E14599DB8C FOREIGN KEY (source_page_id) REFERENCES landing_page (id)');
        $this->addSql('ALTER TABLE enrollment ADD CONSTRAINT FK_DBDCD7E19B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id)');
        $this->addSql('ALTER TABLE plan ADD CONSTRAINT FK_DD5A5B7D9B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id)');
        $this->addSql('ALTER TABLE session ADD CONSTRAINT FK_D044D5D48F7DB25B FOREIGN KEY (enrollment_id) REFERENCES enrollment (id)');
        $this->addSql('ALTER TABLE session ADD CONSTRAINT FK_D044D5D4E7A1254A FOREIGN KEY (contact_id) REFERENCES contact (id)');
        $this->addSql('ALTER TABLE session ADD CONSTRAINT FK_D044D5D49B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id)');
        $this->addSql('ALTER TABLE session_reminder ADD CONSTRAINT FK_CB9B2F28613FECDF FOREIGN KEY (session_id) REFERENCES session (id)');
        $this->addSql('ALTER TABLE session_reminder ADD CONSTRAINT FK_CB9B2F289B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE availability DROP FOREIGN KEY FK_3FB7A2BF9B6B5FBA');
        $this->addSql('ALTER TABLE enrollment DROP FOREIGN KEY FK_DBDCD7E1E7A1254A');
        $this->addSql('ALTER TABLE enrollment DROP FOREIGN KEY FK_DBDCD7E1E899029B');
        $this->addSql('ALTER TABLE enrollment DROP FOREIGN KEY FK_DBDCD7E14599DB8C');
        $this->addSql('ALTER TABLE enrollment DROP FOREIGN KEY FK_DBDCD7E19B6B5FBA');
        $this->addSql('ALTER TABLE plan DROP FOREIGN KEY FK_DD5A5B7D9B6B5FBA');
        $this->addSql('ALTER TABLE session DROP FOREIGN KEY FK_D044D5D48F7DB25B');
        $this->addSql('ALTER TABLE session DROP FOREIGN KEY FK_D044D5D4E7A1254A');
        $this->addSql('ALTER TABLE session DROP FOREIGN KEY FK_D044D5D49B6B5FBA');
        $this->addSql('ALTER TABLE session_reminder DROP FOREIGN KEY FK_CB9B2F28613FECDF');
        $this->addSql('ALTER TABLE session_reminder DROP FOREIGN KEY FK_CB9B2F289B6B5FBA');
        $this->addSql('DROP TABLE availability');
        $this->addSql('DROP TABLE enrollment');
        $this->addSql('DROP TABLE plan');
        $this->addSql('DROP TABLE session');
        $this->addSql('DROP TABLE session_reminder');
    }
}
