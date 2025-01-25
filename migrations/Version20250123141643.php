<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250123141643 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE project_message_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE project_message (id INT NOT NULL, name VARCHAR(255) NOT NULL, message TEXT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE project_message_project (project_message_id INT NOT NULL, project_id INT NOT NULL, PRIMARY KEY(project_message_id, project_id))');
        $this->addSql('CREATE INDEX IDX_870A9F124BDC681B ON project_message_project (project_message_id)');
        $this->addSql('CREATE INDEX IDX_870A9F12166D1F9C ON project_message_project (project_id)');
        $this->addSql('ALTER TABLE project_message_project ADD CONSTRAINT FK_870A9F124BDC681B FOREIGN KEY (project_message_id) REFERENCES project_message (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE project_message_project ADD CONSTRAINT FK_870A9F12166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE project_message_id_seq CASCADE');
        $this->addSql('ALTER TABLE project_message_project DROP CONSTRAINT FK_870A9F124BDC681B');
        $this->addSql('ALTER TABLE project_message_project DROP CONSTRAINT FK_870A9F12166D1F9C');
        $this->addSql('DROP TABLE project_message');
        $this->addSql('DROP TABLE project_message_project');
    }
}
