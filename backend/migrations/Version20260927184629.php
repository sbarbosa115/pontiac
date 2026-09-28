<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260927184629 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Pages and leads: landing pages and their former addresses, the media library, lead categories, contacts and the forms they sent, and each consultant\'s privacy policy.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE account_slug_redirect (old_slug VARCHAR(60) NOT NULL, id BINARY(16) NOT NULL, account_id BINARY(16) NOT NULL, UNIQUE INDEX UNIQ_DBF65AFB0001AC7 (old_slug), INDEX IDX_DBF65AF9B6B5FBA (account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE contact (full_name VARCHAR(180) NOT NULL, email VARCHAR(180) NOT NULL, phone VARCHAR(40) DEFAULT NULL, status VARCHAR(10) NOT NULL, consent_at DATETIME NOT NULL, consent_policy_hash VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL, last_activity_at DATETIME NOT NULL, anonymized_at DATETIME DEFAULT NULL, id BINARY(16) NOT NULL, category_id BINARY(16) DEFAULT NULL, source_page_id BINARY(16) DEFAULT NULL, account_id BINARY(16) NOT NULL, INDEX idx_contact_account_activity (account_id, last_activity_at), UNIQUE INDEX uniq_contact_account_email (account_id, email), INDEX IDX_4C62E63812469DE2 (category_id), INDEX IDX_4C62E6384599DB8C (source_page_id), INDEX IDX_4C62E6389B6B5FBA (account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE landing_page (title VARCHAR(160) NOT NULL, slug VARCHAR(60) NOT NULL, template VARCHAR(30) NOT NULL, home TINYINT NOT NULL, status VARCHAR(10) NOT NULL, draft JSON NOT NULL, published JSON DEFAULT NULL, published_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, id BINARY(16) NOT NULL, account_id BINARY(16) NOT NULL, INDEX idx_page_account_status (account_id, status), UNIQUE INDEX uniq_page_account_slug (account_id, slug), INDEX IDX_87A7C8999B6B5FBA (account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE lead_category (name VARCHAR(80) NOT NULL, color VARCHAR(20) NOT NULL, active TINYINT NOT NULL, id BINARY(16) NOT NULL, account_id BINARY(16) NOT NULL, INDEX idx_lead_category_account (account_id, name), INDEX IDX_AC1F9BC29B6B5FBA (account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE lead_submission (answers JSON NOT NULL, utm JSON NOT NULL, referrer VARCHAR(500) DEFAULT NULL, submitted_at DATETIME NOT NULL, id BINARY(16) NOT NULL, contact_id BINARY(16) NOT NULL, page_id BINARY(16) NOT NULL, account_id BINARY(16) NOT NULL, INDEX idx_submission_account_page_at (account_id, page_id, submitted_at), INDEX idx_submission_account_contact_at (account_id, contact_id, submitted_at), INDEX IDX_F39E416FE7A1254A (contact_id), INDEX IDX_F39E416FC4663E4 (page_id), INDEX IDX_F39E416F9B6B5FBA (account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE media_asset (original_name VARCHAR(255) NOT NULL, content_type VARCHAR(40) NOT NULL, total_bytes INT NOT NULL, width SMALLINT NOT NULL, height SMALLINT NOT NULL, variants JSON NOT NULL, alt_text VARCHAR(255) NOT NULL, active TINYINT NOT NULL, created_at DATETIME NOT NULL, id BINARY(16) NOT NULL, account_id BINARY(16) NOT NULL, INDEX idx_media_account_created (account_id, created_at), INDEX IDX_1DB69EED9B6B5FBA (account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE page_slug_redirect (old_slug VARCHAR(60) NOT NULL, id BINARY(16) NOT NULL, page_id BINARY(16) NOT NULL, account_id BINARY(16) NOT NULL, UNIQUE INDEX uniq_page_redirect_account_slug (account_id, old_slug), INDEX IDX_3432927C4663E4 (page_id), INDEX IDX_34329279B6B5FBA (account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE account_slug_redirect ADD CONSTRAINT FK_DBF65AF9B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id)');
        $this->addSql('ALTER TABLE contact ADD CONSTRAINT FK_4C62E63812469DE2 FOREIGN KEY (category_id) REFERENCES lead_category (id)');
        $this->addSql('ALTER TABLE contact ADD CONSTRAINT FK_4C62E6384599DB8C FOREIGN KEY (source_page_id) REFERENCES landing_page (id)');
        $this->addSql('ALTER TABLE contact ADD CONSTRAINT FK_4C62E6389B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id)');
        $this->addSql('ALTER TABLE landing_page ADD CONSTRAINT FK_87A7C8999B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id)');
        $this->addSql('ALTER TABLE lead_category ADD CONSTRAINT FK_AC1F9BC29B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id)');
        $this->addSql('ALTER TABLE lead_submission ADD CONSTRAINT FK_F39E416FE7A1254A FOREIGN KEY (contact_id) REFERENCES contact (id)');
        $this->addSql('ALTER TABLE lead_submission ADD CONSTRAINT FK_F39E416FC4663E4 FOREIGN KEY (page_id) REFERENCES landing_page (id)');
        $this->addSql('ALTER TABLE lead_submission ADD CONSTRAINT FK_F39E416F9B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id)');
        $this->addSql('ALTER TABLE media_asset ADD CONSTRAINT FK_1DB69EED9B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id)');
        $this->addSql('ALTER TABLE page_slug_redirect ADD CONSTRAINT FK_3432927C4663E4 FOREIGN KEY (page_id) REFERENCES landing_page (id)');
        $this->addSql('ALTER TABLE page_slug_redirect ADD CONSTRAINT FK_34329279B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id)');
        // Existing consultants start with no text of their own (the platform's default is shown): added, filled, required.
        $this->addSql('ALTER TABLE account ADD privacy_text LONGTEXT DEFAULT NULL');
        $this->addSql("UPDATE account SET privacy_text = ''");
        $this->addSql('ALTER TABLE account MODIFY privacy_text LONGTEXT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE account_slug_redirect DROP FOREIGN KEY FK_DBF65AF9B6B5FBA');
        $this->addSql('ALTER TABLE contact DROP FOREIGN KEY FK_4C62E63812469DE2');
        $this->addSql('ALTER TABLE contact DROP FOREIGN KEY FK_4C62E6384599DB8C');
        $this->addSql('ALTER TABLE contact DROP FOREIGN KEY FK_4C62E6389B6B5FBA');
        $this->addSql('ALTER TABLE landing_page DROP FOREIGN KEY FK_87A7C8999B6B5FBA');
        $this->addSql('ALTER TABLE lead_category DROP FOREIGN KEY FK_AC1F9BC29B6B5FBA');
        $this->addSql('ALTER TABLE lead_submission DROP FOREIGN KEY FK_F39E416FE7A1254A');
        $this->addSql('ALTER TABLE lead_submission DROP FOREIGN KEY FK_F39E416FC4663E4');
        $this->addSql('ALTER TABLE lead_submission DROP FOREIGN KEY FK_F39E416F9B6B5FBA');
        $this->addSql('ALTER TABLE media_asset DROP FOREIGN KEY FK_1DB69EED9B6B5FBA');
        $this->addSql('ALTER TABLE page_slug_redirect DROP FOREIGN KEY FK_3432927C4663E4');
        $this->addSql('ALTER TABLE page_slug_redirect DROP FOREIGN KEY FK_34329279B6B5FBA');
        $this->addSql('DROP TABLE account_slug_redirect');
        $this->addSql('DROP TABLE contact');
        $this->addSql('DROP TABLE landing_page');
        $this->addSql('DROP TABLE lead_category');
        $this->addSql('DROP TABLE lead_submission');
        $this->addSql('DROP TABLE media_asset');
        $this->addSql('DROP TABLE page_slug_redirect');
        $this->addSql('ALTER TABLE account DROP privacy_text');
    }
}
