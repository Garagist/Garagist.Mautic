<?php

declare(strict_types=1);

namespace Neos\Flow\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20210627155736 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE garagist_mautic_domain_model_mauticemail (persistence_object_identifier VARCHAR(40) NOT NULL, templateurl VARCHAR(255) NOT NULL, emailidentifier VARCHAR(255) NOT NULL, nodeidentifier VARCHAR(255) NOT NULL, published TINYINT(1) NOT NULL, datecreated DATETIME NOT NULL, datemodified DATETIME DEFAULT NULL, datesent DATETIME DEFAULT NULL, task VARCHAR(255) NOT NULL, PRIMARY KEY(persistence_object_identifier)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE garagist_mautic_domain_model_mauticemail');
    }
}
