<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260527123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Only expose Bpost parcel lockers in Belgium.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE delivery_method SET active = 0 WHERE provider = 'bpost' AND method = 'parcel_locker' AND country_code <> 'BE'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE delivery_method SET active = 1 WHERE provider = 'bpost' AND method = 'parcel_locker' AND country_code <> 'BE'");
    }
}
