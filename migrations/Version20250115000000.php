<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration for User and ActivityLog entities
 */
final class Version20250115000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create User and ActivityLog tables for admin system';
    }

    public function up(Schema $schema): void
    {
        // Create user table
        $this->addSql('CREATE TABLE `user` (
            id INT AUTO_INCREMENT NOT NULL,
            email VARCHAR(180) NOT NULL,
            username VARCHAR(180) NOT NULL,
            roles JSON NOT NULL,
            password VARCHAR(255) NOT NULL,
            name VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL,
            is_active TINYINT(1) NOT NULL,
            UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email),
            UNIQUE INDEX UNIQ_IDENTIFIER_USERNAME (username),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Create activity_log table
        $this->addSql('CREATE TABLE activity_log (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT DEFAULT NULL,
            action VARCHAR(255) NOT NULL,
            entity_type VARCHAR(255) NOT NULL,
            entity_id INT DEFAULT NULL,
            affected_data LONGTEXT DEFAULT NULL,
            description LONGTEXT DEFAULT NULL,
            timestamp DATETIME NOT NULL,
            ip_address VARCHAR(255) DEFAULT NULL,
            INDEX IDX_ACTIVITY_LOG_USER (user_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT FK_ACTIVITY_LOG_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_log DROP FOREIGN KEY FK_ACTIVITY_LOG_USER');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE activity_log');
    }
}

