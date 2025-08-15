<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250813182358 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_39f0f3d8772e836a');
        $this->addSql('ALTER TABLE user_secret RENAME COLUMN userId TO user_id');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_39F0F3D8A76ED395 ON user_secret (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP INDEX UNIQ_39F0F3D8A76ED395');
        $this->addSql('ALTER TABLE user_secret RENAME COLUMN user_id TO userId');
        $this->addSql('CREATE UNIQUE INDEX uniq_39f0f3d8772e836a ON user_secret (userId)');
    }
}
