<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260320111941 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE customer ADD created_by_id INT DEFAULT NULL, ADD phone VARCHAR(255) DEFAULT NULL, ADD username VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE customer ADD CONSTRAINT FK_81398E09B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('CREATE INDEX IDX_81398E09B03A8386 ON customer (created_by_id)');
        $this->addSql('SET @stock_exists_20111941 := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = \'product\' AND column_name = \'stock\')');
        $this->addSql('SET @stock_sql_20111941 := IF(@stock_exists_20111941 > 0, \'ALTER TABLE product CHANGE stock stock INT NOT NULL\', \'SELECT 1\')');
        $this->addSql('PREPARE stmt_stock_20111941 FROM @stock_sql_20111941');
        $this->addSql('EXECUTE stmt_stock_20111941');
        $this->addSql('DEALLOCATE PREPARE stmt_stock_20111941');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product CHANGE stock stock INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE customer DROP FOREIGN KEY FK_81398E09B03A8386');
        $this->addSql('DROP INDEX IDX_81398E09B03A8386 ON customer');
        $this->addSql('ALTER TABLE customer DROP created_by_id, DROP phone, DROP username');
    }
}
