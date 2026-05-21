<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260320120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('SET @stock_exists_20120000 := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = \'product\' AND column_name = \'stock\')');
        $this->addSql('SET @stock_sql_20120000 := IF(@stock_exists_20120000 = 0, \'ALTER TABLE product ADD stock INT NOT NULL DEFAULT 0\', \'SELECT 1\')');
        $this->addSql('PREPARE stmt_stock_20120000 FROM @stock_sql_20120000');
        $this->addSql('EXECUTE stmt_stock_20120000');
        $this->addSql('DEALLOCATE PREPARE stmt_stock_20120000');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP stock');
    }
}

