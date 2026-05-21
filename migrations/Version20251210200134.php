<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251210200134 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        // Split the original statement into conditional pieces.
        // Your DB schema may already not have `phone`/`address` columns, so unconditional DROP causes failures.
        $this->addSql('SET @customer_name_exists_20134 := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = \'customer\' AND column_name = \'customer_name\')');
        $this->addSql('SET @customer_name_sql_20134 := IF(@customer_name_exists_20134 = 0, \'ALTER TABLE customer ADD customer_name VARCHAR(255) NOT NULL\', \'SELECT 1\')');
        $this->addSql('PREPARE stmt_customer_name_20134 FROM @customer_name_sql_20134');
        $this->addSql('EXECUTE stmt_customer_name_20134');
        $this->addSql('DEALLOCATE PREPARE stmt_customer_name_20134');

        $this->addSql('SET @phone_exists_20134 := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = \'customer\' AND column_name = \'phone\')');
        $this->addSql('SET @phone_sql_20134 := IF(@phone_exists_20134 = 1, \'ALTER TABLE customer DROP COLUMN phone\', \'SELECT 1\')');
        $this->addSql('PREPARE stmt_phone_20134 FROM @phone_sql_20134');
        $this->addSql('EXECUTE stmt_phone_20134');
        $this->addSql('DEALLOCATE PREPARE stmt_phone_20134');

        $this->addSql('SET @address_exists_20134 := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = \'customer\' AND column_name = \'address\')');
        $this->addSql('SET @address_sql_20134 := IF(@address_exists_20134 = 1, \'ALTER TABLE customer DROP COLUMN address\', \'SELECT 1\')');
        $this->addSql('PREPARE stmt_address_20134 FROM @address_sql_20134');
        $this->addSql('EXECUTE stmt_address_20134');
        $this->addSql('DEALLOCATE PREPARE stmt_address_20134');

        // Only set email to NOT NULL if the column exists and there are no NULL values.
        $this->addSql('SET @email_null_count_20134 := (SELECT COUNT(*) FROM customer WHERE email IS NULL)');
        $this->addSql('SET @email_nullable_20134 := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = \'customer\' AND column_name = \'email\' AND is_nullable = \'YES\')');
        $this->addSql('SET @email_sql_20134 := IF(@email_nullable_20134 = 1 AND @email_null_count_20134 = 0, \'ALTER TABLE customer MODIFY email VARCHAR(255) NOT NULL\', \'SELECT 1\')');
        $this->addSql('PREPARE stmt_email_20134 FROM @email_sql_20134');
        $this->addSql('EXECUTE stmt_email_20134');
        $this->addSql('DEALLOCATE PREPARE stmt_email_20134');

        $this->addSql('ALTER TABLE `order` CHANGE customer_id customer_id INT DEFAULT NULL');
        // Avoid duplicate FK/index errors if this migration partially executed before failing.
        $this->addSql('SET @fk_exists_20134 := (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = \'order\' AND constraint_name = \'FK_F52993989395C3F3\')');
        $this->addSql('SET @fk_sql_20134 := IF(@fk_exists_20134 = 0, \'ALTER TABLE `order` ADD CONSTRAINT FK_F52993989395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id)\', \'SELECT 1\')');
        $this->addSql('PREPARE stmt_fk_20134 FROM @fk_sql_20134');
        $this->addSql('EXECUTE stmt_fk_20134');
        $this->addSql('DEALLOCATE PREPARE stmt_fk_20134');

        $this->addSql('SET @idx_exists_20134 := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = \'order\' AND index_name = \'IDX_F52993989395C3F3\')');
        $this->addSql('SET @idx_sql_20134 := IF(@idx_exists_20134 = 0, \'CREATE INDEX IDX_F52993989395C3F3 ON `order` (customer_id)\', \'SELECT 1\')');
        $this->addSql('PREPARE stmt_idx_20134 FROM @idx_sql_20134');
        $this->addSql('EXECUTE stmt_idx_20134');
        $this->addSql('DEALLOCATE PREPARE stmt_idx_20134');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE customer ADD phone VARCHAR(20) DEFAULT NULL, ADD address VARCHAR(255) DEFAULT NULL, DROP customer_name, CHANGE email email VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F52993989395C3F3');
        $this->addSql('DROP INDEX IDX_F52993989395C3F3 ON `order`');
        $this->addSql('ALTER TABLE `order` CHANGE customer_id customer_id INT NOT NULL');
    }
}
