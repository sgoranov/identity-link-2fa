<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240703131627 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE auth_request (id UUID NOT NULL, userId TEXT NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expired TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, authenticated TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN auth_request.id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE user_secret (id UUID NOT NULL, userId TEXT NOT NULL, secret TEXT NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, reset_secret_on_next_auth BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN user_secret.id IS \'(DC2Type:uuid)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE auth_request');
        $this->addSql('DROP TABLE user_secret');
    }
}
