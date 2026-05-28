<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260521220846 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activity_log RENAME INDEX idx_activity_log_user TO IDX_FD06F647A76ED395');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C1B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('CREATE INDEX IDX_64C19C1B03A8386 ON category (created_by_id)');
        $this->addSql('ALTER TABLE customer DROP created_at');
        $this->addSql('ALTER TABLE `order` ADD created_by_id INT DEFAULT NULL, ADD order_ref VARCHAR(36) DEFAULT NULL, ADD product_id INT DEFAULT NULL, ADD mobile_user_id INT DEFAULT NULL, ADD payment_method VARCHAR(32) DEFAULT NULL, ADD payment_status VARCHAR(32) DEFAULT \'unpaid\' NOT NULL, ADD payment_reference VARCHAR(128) DEFAULT NULL, ADD paid_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F5299398B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('CREATE INDEX IDX_F5299398B03A8386 ON `order` (created_by_id)');
        $this->addSql('ALTER TABLE product ADD created_by_id INT DEFAULT NULL, DROP created_at, CHANGE stock stock INT NOT NULL');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04ADB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('CREATE INDEX IDX_D34A04ADB03A8386 ON product (created_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04ADB03A8386');
        $this->addSql('DROP INDEX IDX_D34A04ADB03A8386 ON product');
        $this->addSql('ALTER TABLE product ADD created_at DATETIME NOT NULL, DROP created_by_id, CHANGE stock stock INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F5299398B03A8386');
        $this->addSql('DROP INDEX IDX_F5299398B03A8386 ON `order`');
        $this->addSql('ALTER TABLE `order` DROP created_by_id, DROP order_ref, DROP product_id, DROP mobile_user_id, DROP payment_method, DROP payment_status, DROP payment_reference, DROP paid_at');
        $this->addSql('ALTER TABLE activity_log RENAME INDEX idx_fd06f647a76ed395 TO IDX_ACTIVITY_LOG_USER');
        $this->addSql('ALTER TABLE customer ADD created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_64C19C1B03A8386');
        $this->addSql('DROP INDEX IDX_64C19C1B03A8386 ON category');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
