<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251210192938 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `order` CHANGE customer_id customer_id INT DEFAULT NULL');
        // Migration may have partially executed previously; only add FK/index if they don't exist.
        $this->addSql('SET @fk_exists := (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = \'order\' AND constraint_name = \'FK_F52993989395C3F3\')');
        $this->addSql('SET @fk_sql := IF(@fk_exists = 0, \'ALTER TABLE `order` ADD CONSTRAINT FK_F52993989395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id)\', \'SELECT 1\')');
        $this->addSql('PREPARE stmt_fk_92938 FROM @fk_sql');
        $this->addSql('EXECUTE stmt_fk_92938');
        $this->addSql('DEALLOCATE PREPARE stmt_fk_92938');

        $this->addSql('SET @idx_exists := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = \'order\' AND index_name = \'IDX_F52993989395C3F3\')');
        $this->addSql('SET @idx_sql := IF(@idx_exists = 0, \'CREATE INDEX IDX_F52993989395C3F3 ON `order` (customer_id)\', \'SELECT 1\')');
        $this->addSql('PREPARE stmt_idx_92938 FROM @idx_sql');
        $this->addSql('EXECUTE stmt_idx_92938');
        $this->addSql('DEALLOCATE PREPARE stmt_idx_92938');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F52993989395C3F3');
        $this->addSql('DROP INDEX IDX_F52993989395C3F3 ON `order`');
        $this->addSql('ALTER TABLE `order` CHANGE customer_id customer_id INT NOT NULL');
    }
}
