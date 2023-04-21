<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230421064114 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX IDX_BD335AC23A733244 ON spi_spices');
        $this->addSql('DROP INDEX IDX_BD335AC248763A81 ON spi_spices');
        $this->addSql('ALTER TABLE spi_spices DROP agr_id_id, DROP sty_id_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE spi_spices ADD agr_id_id INT NOT NULL, ADD sty_id_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_BD335AC23A733244 ON spi_spices (sty_id_id)');
        $this->addSql('CREATE INDEX IDX_BD335AC248763A81 ON spi_spices (agr_id_id)');
    }
}
