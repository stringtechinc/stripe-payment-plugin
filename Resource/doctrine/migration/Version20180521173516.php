<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180521173516 extends AbstractMigration
{

    const NAME = 'plg_stripe_payment_config';

    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        if ($schema->hasTable(self::NAME)) {
            return true;
        }
        $table = $schema->createTable(self::NAME);
        $table->addColumn('stripe_payment_id', 'integer', array(
            'autoincrement' => true
        ));
        $table->addColumn('live_public_key', 'text', array(
            'notnull' => false
        ));
        $table->addColumn('live_secret_key', 'text', array(
            'notnull' => false
        ));
        $table->addColumn('test_public_key', 'text', array(
            'notnull' => false
        ));
        $table->addColumn('test_secret_key', 'text', array(
            'notnull' => false
        ));
        $table->addColumn('payment_id', 'integer', array(
            'notnull' => false,
            'unsigned' => true,
        ));
        $table->setPrimaryKey(array('stripe_payment_id'));
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        if (!$schema->hasTable(self::NAME)) {
            return true;
        }
        $schema->dropTable(self::NAME);
    }
}
