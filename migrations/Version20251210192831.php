<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251210192831 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE IF NOT EXISTS customer (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) DEFAULT NULL, phone VARCHAR(20) DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Add `customer_id` only if it doesn't exist (the migration may have partially executed before failing).
        $this->addSql('SET @customer_id_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = \'order\' AND column_name = \'customer_id\')');
        $this->addSql('SET @customer_id_sql := IF(@customer_id_exists = 0, \'ALTER TABLE `order` ADD customer_id INT NOT NULL\', \'SELECT 1\')');
        $this->addSql('PREPARE stmt_customer_id FROM @customer_id_sql');
        $this->addSql('EXECUTE stmt_customer_id');
        $this->addSql('DEALLOCATE PREPARE stmt_customer_id');

        // Your current schema may already not have `customer_name`.
        $this->addSql('SET @customer_name_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = \'order\' AND column_name = \'customer_name\')');
        $this->addSql('SET @customer_name_sql := IF(@customer_name_exists = 1, \'ALTER TABLE `order` DROP COLUMN customer_name\', \'SELECT 1\')');
        $this->addSql('PREPARE stmt_customer_name FROM @customer_name_sql');
        $this->addSql('EXECUTE stmt_customer_name');
        $this->addSql('DEALLOCATE PREPARE stmt_customer_name');

        // Add the FK constraint only if it doesn't exist.
        $this->addSql('SET @fk_exists := (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = \'order\' AND constraint_name = \'FK_F52993989395C3F3\')');
        $this->addSql('SET @fk_sql := IF(@fk_exists = 0, \'ALTER TABLE `order` ADD CONSTRAINT FK_F52993989395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id)\', \'SELECT 1\')');
        $this->addSql('PREPARE stmt_fk FROM @fk_sql');
        $this->addSql('EXECUTE stmt_fk');
        $this->addSql('DEALLOCATE PREPARE stmt_fk');

        // Add the index only if it doesn't exist.
        $this->addSql('SET @idx_exists := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = \'order\' AND index_name = \'IDX_F52993989395C3F3\')');
        $this->addSql('SET @idx_sql := IF(@idx_exists = 0, \'CREATE INDEX IDX_F52993989395C3F3 ON `order` (customer_id)\', \'SELECT 1\')');
        $this->addSql('PREPARE stmt_idx FROM @idx_sql');
        $this->addSql('EXECUTE stmt_idx');
        $this->addSql('DEALLOCATE PREPARE stmt_idx');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F52993989395C3F3');
        $this->addSql('DROP TABLE customer');
        $this->addSql('DROP INDEX IDX_F52993989395C3F3 ON `order`');
        // Split to avoid failures if the column already exists/doesn't exist on rollback.
        $this->addSql('ALTER TABLE `order` ADD customer_name VARCHAR(255) NOT NULL');
        // MySQL may not support DROP COLUMN IF EXISTS; use a conditional drop.
        $this->addSql('SET @customer_id_exists_down := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = \'order\' AND column_name = \'customer_id\')');
        $this->addSql('SET @customer_id_sql_down := IF(@customer_id_exists_down = 1, \'ALTER TABLE `order` DROP COLUMN customer_id\', \'SELECT 1\')');
        $this->addSql('PREPARE stmt_customer_id_down FROM @customer_id_sql_down');
        $this->addSql('EXECUTE stmt_customer_id_down');
        $this->addSql('DEALLOCATE PREPARE stmt_customer_id_down');
    }
}
