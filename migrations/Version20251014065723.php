<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create category table and link product.category_id when missing (fresh DB safe).
 */
final class Version20251014065723 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create category table and add product.category_id when present';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS category (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            created_by_id INT DEFAULT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('SET @product_name_exists_65723 := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = \'product\' AND column_name = \'product_name\')');
        $this->addSql('SET @rename_product_name_sql_65723 := IF(@product_name_exists_65723 > 0, \'ALTER TABLE product CHANGE product_name name VARCHAR(255) NOT NULL\', \'SELECT 1\')');
        $this->addSql('PREPARE stmt_rename_product_name_65723 FROM @rename_product_name_sql_65723');
        $this->addSql('EXECUTE stmt_rename_product_name_65723');
        $this->addSql('DEALLOCATE PREPARE stmt_rename_product_name_65723');

        $this->addSql('SET @category_id_exists_65723 := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = \'product\' AND column_name = \'category_id\')');
        $this->addSql('SET @category_id_sql_65723 := IF(@category_id_exists_65723 = 0, \'ALTER TABLE product ADD category_id INT DEFAULT NULL\', \'SELECT 1\')');
        $this->addSql('PREPARE stmt_category_id_65723 FROM @category_id_sql_65723');
        $this->addSql('EXECUTE stmt_category_id_65723');
        $this->addSql('DEALLOCATE PREPARE stmt_category_id_65723');

        $this->addSql('SET @category_table_exists_65723 := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = \'category\')');
        $this->addSql('SET @product_fk_exists_65723 := (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = \'product\' AND constraint_name = \'FK_D34A04AD12469DE2\')');
        $this->addSql('SET @product_fk_sql_65723 := IF(@category_table_exists_65723 > 0 AND @product_fk_exists_65723 = 0, \'ALTER TABLE product ADD CONSTRAINT FK_D34A04AD12469DE2 FOREIGN KEY (category_id) REFERENCES category (id)\', \'SELECT 1\')');
        $this->addSql('PREPARE stmt_product_fk_65723 FROM @product_fk_sql_65723');
        $this->addSql('EXECUTE stmt_product_fk_65723');
        $this->addSql('DEALLOCATE PREPARE stmt_product_fk_65723');

        $this->addSql('SET @product_idx_exists_65723 := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = \'product\' AND index_name = \'IDX_D34A04AD12469DE2\')');
        $this->addSql('SET @product_idx_sql_65723 := IF(@product_idx_exists_65723 = 0, \'CREATE INDEX IDX_D34A04AD12469DE2 ON product (category_id)\', \'SELECT 1\')');
        $this->addSql('PREPARE stmt_product_idx_65723 FROM @product_idx_sql_65723');
        $this->addSql('EXECUTE stmt_product_idx_65723');
        $this->addSql('DEALLOCATE PREPARE stmt_product_idx_65723');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04AD12469DE2');
        $this->addSql('DROP INDEX IDX_D34A04AD12469DE2 ON product');
        $this->addSql('ALTER TABLE product DROP category_id');
        $this->addSql('DROP TABLE category');
    }
}
