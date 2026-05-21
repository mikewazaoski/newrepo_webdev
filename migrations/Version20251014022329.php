<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Create Order table and keep Product intact
 */
final class Version20251014022329 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create order table without dropping existing tables';
    }

    public function up(Schema $schema): void
    {
        // Create order table
        $this->addSql('
            CREATE TABLE `order` (
                id INT AUTO_INCREMENT NOT NULL,
                customer_name VARCHAR(255) NOT NULL,
                product_name VARCHAR(255) NOT NULL,
                quantity DOUBLE PRECISION NOT NULL,
                price DOUBLE PRECISION NOT NULL,
                status VARCHAR(255) NOT NULL,
                order_date DATETIME NOT NULL,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');
    }

    public function down(Schema $schema): void
    {
        // Drop order table if rollback
        $this->addSql('DROP TABLE IF EXISTS `order`');
    }
}
