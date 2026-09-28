<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Flows: flows, stages, arrows, people's places and history, email templates; contacts' opt-out. Additive. */
final class Version20260927222000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Flows, flow stages and transitions, contact flow states and events, email templates; contact flow_emails_stopped_at (additive)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE contact_flow_state (entered_at DATETIME NOT NULL, id BINARY(16) NOT NULL, contact_id BINARY(16) NOT NULL, flow_id BINARY(16) NOT NULL, stage_id BINARY(16) NOT NULL, account_id BINARY(16) NOT NULL, INDEX idx_contact_flow_account_stage (account_id, stage_id), UNIQUE INDEX uniq_contact_flow (contact_id, flow_id), INDEX IDX_FAF0F410E7A1254A (contact_id), INDEX IDX_FAF0F4107EB60D1B (flow_id), INDEX IDX_FAF0F4102298D193 (stage_id), INDEX IDX_FAF0F4109B6B5FBA (account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE email_template (name VARCHAR(120) NOT NULL, subject VARCHAR(200) NOT NULL, body LONGTEXT NOT NULL, active TINYINT NOT NULL, id BINARY(16) NOT NULL, account_id BINARY(16) NOT NULL, INDEX idx_email_template_account_name (account_id, name), INDEX IDX_9C0600CA9B6B5FBA (account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE flow (name VARCHAR(120) NOT NULL, active TINYINT NOT NULL, created_at DATETIME NOT NULL, id BINARY(16) NOT NULL, account_id BINARY(16) NOT NULL, INDEX idx_flow_account_name (account_id, name), INDEX IDX_52C0D6709B6B5FBA (account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE flow_event (flow_name VARCHAR(120) NOT NULL, from_stage VARCHAR(80) DEFAULT NULL, to_stage VARCHAR(80) DEFAULT NULL, reason VARCHAR(30) NOT NULL, email_subject VARCHAR(200) DEFAULT NULL, created_at DATETIME NOT NULL, id BINARY(16) NOT NULL, contact_id BINARY(16) NOT NULL, flow_id BINARY(16) NOT NULL, by_user_id BINARY(16) DEFAULT NULL, account_id BINARY(16) NOT NULL, INDEX idx_flow_event_account_contact (account_id, contact_id, created_at), INDEX IDX_A57475DBE7A1254A (contact_id), INDEX IDX_A57475DB7EB60D1B (flow_id), INDEX IDX_A57475DBDC9C2434 (by_user_id), INDEX IDX_A57475DB9B6B5FBA (account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE flow_stage (name VARCHAR(80) NOT NULL, kind VARCHAR(10) NOT NULL, position SMALLINT NOT NULL, x INT NOT NULL, y INT NOT NULL, alert_days SMALLINT DEFAULT NULL, id BINARY(16) NOT NULL, flow_id BINARY(16) NOT NULL, email_template_id BINARY(16) DEFAULT NULL, account_id BINARY(16) NOT NULL, INDEX idx_flow_stage_account_flow (account_id, flow_id), INDEX IDX_5CA6EC157EB60D1B (flow_id), INDEX IDX_5CA6EC15131A730F (email_template_id), INDEX IDX_5CA6EC159B6B5FBA (account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE flow_transition (trigger_event VARCHAR(30) NOT NULL, id BINARY(16) NOT NULL, flow_id BINARY(16) NOT NULL, from_stage_id BINARY(16) NOT NULL, to_stage_id BINARY(16) NOT NULL, account_id BINARY(16) NOT NULL, INDEX idx_flow_transition_account_flow (account_id, flow_id), INDEX IDX_97B70D2C7EB60D1B (flow_id), INDEX IDX_97B70D2C64773109 (from_stage_id), INDEX IDX_97B70D2CEFC14F3D (to_stage_id), INDEX IDX_97B70D2C9B6B5FBA (account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE contact_flow_state ADD CONSTRAINT FK_FAF0F410E7A1254A FOREIGN KEY (contact_id) REFERENCES contact (id)');
        $this->addSql('ALTER TABLE contact_flow_state ADD CONSTRAINT FK_FAF0F4107EB60D1B FOREIGN KEY (flow_id) REFERENCES flow (id)');
        $this->addSql('ALTER TABLE contact_flow_state ADD CONSTRAINT FK_FAF0F4102298D193 FOREIGN KEY (stage_id) REFERENCES flow_stage (id)');
        $this->addSql('ALTER TABLE contact_flow_state ADD CONSTRAINT FK_FAF0F4109B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id)');
        $this->addSql('ALTER TABLE email_template ADD CONSTRAINT FK_9C0600CA9B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id)');
        $this->addSql('ALTER TABLE flow ADD CONSTRAINT FK_52C0D6709B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id)');
        $this->addSql('ALTER TABLE flow_event ADD CONSTRAINT FK_A57475DBE7A1254A FOREIGN KEY (contact_id) REFERENCES contact (id)');
        $this->addSql('ALTER TABLE flow_event ADD CONSTRAINT FK_A57475DB7EB60D1B FOREIGN KEY (flow_id) REFERENCES flow (id)');
        $this->addSql('ALTER TABLE flow_event ADD CONSTRAINT FK_A57475DBDC9C2434 FOREIGN KEY (by_user_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE flow_event ADD CONSTRAINT FK_A57475DB9B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id)');
        $this->addSql('ALTER TABLE flow_stage ADD CONSTRAINT FK_5CA6EC157EB60D1B FOREIGN KEY (flow_id) REFERENCES flow (id)');
        $this->addSql('ALTER TABLE flow_stage ADD CONSTRAINT FK_5CA6EC15131A730F FOREIGN KEY (email_template_id) REFERENCES email_template (id)');
        $this->addSql('ALTER TABLE flow_stage ADD CONSTRAINT FK_5CA6EC159B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id)');
        $this->addSql('ALTER TABLE flow_transition ADD CONSTRAINT FK_97B70D2C7EB60D1B FOREIGN KEY (flow_id) REFERENCES flow (id)');
        $this->addSql('ALTER TABLE flow_transition ADD CONSTRAINT FK_97B70D2C64773109 FOREIGN KEY (from_stage_id) REFERENCES flow_stage (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE flow_transition ADD CONSTRAINT FK_97B70D2CEFC14F3D FOREIGN KEY (to_stage_id) REFERENCES flow_stage (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE flow_transition ADD CONSTRAINT FK_97B70D2C9B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id)');
        $this->addSql('ALTER TABLE contact ADD flow_emails_stopped_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact_flow_state DROP FOREIGN KEY FK_FAF0F410E7A1254A');
        $this->addSql('ALTER TABLE contact_flow_state DROP FOREIGN KEY FK_FAF0F4107EB60D1B');
        $this->addSql('ALTER TABLE contact_flow_state DROP FOREIGN KEY FK_FAF0F4102298D193');
        $this->addSql('ALTER TABLE contact_flow_state DROP FOREIGN KEY FK_FAF0F4109B6B5FBA');
        $this->addSql('ALTER TABLE email_template DROP FOREIGN KEY FK_9C0600CA9B6B5FBA');
        $this->addSql('ALTER TABLE flow DROP FOREIGN KEY FK_52C0D6709B6B5FBA');
        $this->addSql('ALTER TABLE flow_event DROP FOREIGN KEY FK_A57475DBE7A1254A');
        $this->addSql('ALTER TABLE flow_event DROP FOREIGN KEY FK_A57475DB7EB60D1B');
        $this->addSql('ALTER TABLE flow_event DROP FOREIGN KEY FK_A57475DBDC9C2434');
        $this->addSql('ALTER TABLE flow_event DROP FOREIGN KEY FK_A57475DB9B6B5FBA');
        $this->addSql('ALTER TABLE flow_stage DROP FOREIGN KEY FK_5CA6EC157EB60D1B');
        $this->addSql('ALTER TABLE flow_stage DROP FOREIGN KEY FK_5CA6EC15131A730F');
        $this->addSql('ALTER TABLE flow_stage DROP FOREIGN KEY FK_5CA6EC159B6B5FBA');
        $this->addSql('ALTER TABLE flow_transition DROP FOREIGN KEY FK_97B70D2C7EB60D1B');
        $this->addSql('ALTER TABLE flow_transition DROP FOREIGN KEY FK_97B70D2C64773109');
        $this->addSql('ALTER TABLE flow_transition DROP FOREIGN KEY FK_97B70D2CEFC14F3D');
        $this->addSql('ALTER TABLE flow_transition DROP FOREIGN KEY FK_97B70D2C9B6B5FBA');
        $this->addSql('DROP TABLE contact_flow_state');
        $this->addSql('DROP TABLE email_template');
        $this->addSql('DROP TABLE flow');
        $this->addSql('DROP TABLE flow_event');
        $this->addSql('DROP TABLE flow_stage');
        $this->addSql('DROP TABLE flow_transition');
        $this->addSql('ALTER TABLE contact DROP flow_emails_stopped_at');
    }
}
