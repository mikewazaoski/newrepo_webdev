<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528110600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mark all existing user accounts as verified';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE `user` SET is_verified = 1, verification_token = NULL WHERE is_verified = 0 OR is_verified IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE `user` SET is_verified = 0 WHERE is_verified = 1');
    }
}
