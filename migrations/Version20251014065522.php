<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Remove legacy customer table and product.category_id if they still exist.
 */
final class Version20251014065522 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription(): string
    {
        return 'Drop legacy customer table and product.category_id when present';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('SET @fk_exists_65522 := (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = \'order\' AND constraint_name = \'FK_F52993989395C3F3\')');
        $this->addSql('SET @fk_sql_65522 := IF(@fk_exists_65522 > 0, \'ALTER TABLE `order` DROP FOREIGN KEY FK_F52993989395C3F3\', \'SELECT 1\')');
        $this->addSql('PREPARE stmt_fk_65522 FROM @fk_sql_65522');
        $this->addSql('EXECUTE stmt_fk_65522');
        $this->addSql('DEALLOCATE PREPARE stmt_fk_65522');

        $this->addSql('SET @customer_exists_65522 := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = \'customer\')');
        $this->addSql('SET @drop_customer_sql_65522 := IF(@customer_exists_65522 > 0, \'DROP TABLE customer\', \'SELECT 1\')');
        $this->addSql('PREPARE stmt_customer_65522 FROM @drop_customer_sql_65522');
        $this->addSql('EXECUTE stmt_customer_65522');
        $this->addSql('DEALLOCATE PREPARE stmt_customer_65522');

        $this->addSql('SET @product_fk_exists_65522 := (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = \'product\' AND constraint_name = \'FK_D34A04AD12469DE2\')');
        $this->addSql('SET @product_fk_sql_65522 := IF(@product_fk_exists_65522 > 0, \'ALTER TABLE product DROP FOREIGN KEY FK_D34A04AD12469DE2\', \'SELECT 1\')');
        $this->addSql('PREPARE stmt_product_fk_65522 FROM @product_fk_sql_65522');
        $this->addSql('EXECUTE stmt_product_fk_65522');
        $this->addSql('DEALLOCATE PREPARE stmt_product_fk_65522');

        $this->addSql('SET @product_idx_exists_65522 := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = \'product\' AND index_name = \'IDX_D34A04AD12469DE2\')');
        $this->addSql('SET @product_idx_sql_65522 := IF(@product_idx_exists_65522 > 0, \'DROP INDEX IDX_D34A04AD12469DE2 ON product\', \'SELECT 1\')');
        $this->addSql('PREPARE stmt_product_idx_65522 FROM @product_idx_sql_65522');
        $this->addSql('EXECUTE stmt_product_idx_65522');
        $this->addSql('DEALLOCATE PREPARE stmt_product_idx_65522');

        $this->addSql('SET @category_id_exists_65522 := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = \'product\' AND column_name = \'category_id\')');
        $this->addSql('SET @drop_category_id_sql_65522 := IF(@category_id_exists_65522 > 0, \'ALTER TABLE product DROP category_id\', \'SELECT 1\')');
        $this->addSql('PREPARE stmt_category_id_65522 FROM @drop_category_id_sql_65522');
        $this->addSql('EXECUTE stmt_category_id_65522');
        $this->addSql('DEALLOCATE PREPARE stmt_category_id_65522');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE customer (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, email VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, phone DOUBLE PRECISION NOT NULL, address VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE product ADD category_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04AD12469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_D34A04AD12469DE2 ON product (category_id)');
    }
}
