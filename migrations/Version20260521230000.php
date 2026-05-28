<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260521230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link customer records to mobile user accounts (account_user_id)';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = \'customer\' AND column_name = \'account_user_id\')');
        $this->addSql('SET @sql := IF(@col_exists = 0, \'ALTER TABLE customer ADD account_user_id INT DEFAULT NULL\', \'SELECT 1\')');
        $this->addSql('PREPARE stmt FROM @sql');
        $this->addSql('EXECUTE stmt');
        $this->addSql('DEALLOCATE PREPARE stmt');

        $this->addSql('SET @fk_exists := (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = \'customer\' AND constraint_name = \'FK_CUSTOMER_ACCOUNT_USER\')');
        $this->addSql('SET @fk_sql := IF(@fk_exists = 0, \'ALTER TABLE customer ADD CONSTRAINT FK_CUSTOMER_ACCOUNT_USER FOREIGN KEY (account_user_id) REFERENCES `user` (id) ON DELETE SET NULL\', \'SELECT 1\')');
        $this->addSql('PREPARE stmt_fk FROM @fk_sql');
        $this->addSql('EXECUTE stmt_fk');
        $this->addSql('DEALLOCATE PREPARE stmt_fk');

        $this->addSql('SET @idx_exists := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = \'customer\' AND index_name = \'IDX_CUSTOMER_ACCOUNT_USER\')');
        $this->addSql('SET @idx_sql := IF(@idx_exists = 0, \'CREATE INDEX IDX_CUSTOMER_ACCOUNT_USER ON customer (account_user_id)\', \'SELECT 1\')');
        $this->addSql('PREPARE stmt_idx FROM @idx_sql');
        $this->addSql('EXECUTE stmt_idx');
        $this->addSql('DEALLOCATE PREPARE stmt_idx');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer DROP FOREIGN KEY FK_CUSTOMER_ACCOUNT_USER');
        $this->addSql('DROP INDEX IDX_CUSTOMER_ACCOUNT_USER ON customer');
        $this->addSql('ALTER TABLE customer DROP account_user_id');
    }
}
