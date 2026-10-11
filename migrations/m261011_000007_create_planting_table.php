<?php

use yii\db\Migration;

/**
 * Карточки посадок (ТЗ 5.4).
 * FK main_photo_id добавляется в миграции planting_photo (циклическая зависимость).
 */
class m261011_000007_create_planting_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%planting}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'species_id' => $this->integer()->notNull(),
            'latitude' => $this->decimal(10, 7)->notNull(),
            'longitude' => $this->decimal(10, 7)->notNull(),
            'coords_source' => $this->string(10)->notNull()->defaultValue('manual'),
            'planted_at' => $this->date()->null(),
            'title' => $this->string(150)->null(),
            'description' => $this->text()->null(),
            'status' => $this->string(20)->notNull()->defaultValue('draft'),
            'named_status' => $this->string(20)->notNull()->defaultValue('none'),
            'named_until' => $this->integer()->null(),
            'ad_campaign_id' => $this->integer()->null(),
            'main_photo_id' => $this->integer()->null(),
            'approved_at' => $this->integer()->null(),
            'moderated_by' => $this->integer()->null(),
            'moderation_comment' => $this->text()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('ix_planting_status_coords', '{{%planting}}', ['status', 'latitude', 'longitude']);
        $this->createIndex('ix_planting_user', '{{%planting}}', 'user_id');
        $this->createIndex('ix_planting_campaign', '{{%planting}}', 'ad_campaign_id');
        $this->createIndex('ix_planting_named_status', '{{%planting}}', 'named_status');
        $this->createIndex('ix_planting_species', '{{%planting}}', 'species_id');

        $this->addForeignKey('fk_planting_user', '{{%planting}}', 'user_id', '{{%user}}', 'id', 'RESTRICT', 'CASCADE');
        $this->addForeignKey('fk_planting_species', '{{%planting}}', 'species_id', '{{%tree_species}}', 'id', 'RESTRICT', 'CASCADE');
        $this->addForeignKey('fk_planting_campaign', '{{%planting}}', 'ad_campaign_id', '{{%ad_campaign}}', 'id', 'SET NULL', 'CASCADE');
        $this->addForeignKey('fk_planting_moderator', '{{%planting}}', 'moderated_by', '{{%user}}', 'id', 'SET NULL', 'CASCADE');

        // Опционально: PostGIS. Раскомментировать при наличии расширения.
        // if ($this->db->driverName === 'pgsql') {
        //     $this->execute('CREATE EXTENSION IF NOT EXISTS postgis');
        //     $this->execute('ALTER TABLE {{%planting}} ADD COLUMN geom geography(Point, 4326)');
        //     $this->execute('CREATE INDEX ix_planting_geom ON {{%planting}} USING GIST (geom)');
        // }
    }

    public function safeDown()
    {
        $this->dropTable('{{%planting}}');
    }
}
