<?php

use yii\db\Migration;

/**
 * Рекламные размещения: точка на карте и баннер (ТЗ 5.12).
 */
class m261011_000006_create_ad_placement_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%ad_placement}}', [
            'id' => $this->primaryKey(),
            'campaign_id' => $this->integer()->notNull(),
            'type' => $this->string(10)->notNull(),
            'latitude' => $this->decimal(10, 7)->null(),
            'longitude' => $this->decimal(10, 7)->null(),
            'marker_icon' => $this->string()->null(),
            'image_path' => $this->string()->null(),
            'image_mobile_path' => $this->string()->null(),
            'position' => $this->string(20)->null(),
            'target_url' => $this->string(500)->null(),
            'priority' => $this->integer()->notNull()->defaultValue(0),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'impressions' => $this->integer()->notNull()->defaultValue(0),
            'clicks' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('ix_ad_placement_campaign', '{{%ad_placement}}', 'campaign_id');
        $this->createIndex('ix_ad_placement_type_active', '{{%ad_placement}}', ['type', 'is_active']);
        $this->createIndex('ix_ad_placement_coords', '{{%ad_placement}}', ['latitude', 'longitude']);
        $this->addForeignKey('fk_ad_placement_campaign', '{{%ad_placement}}', 'campaign_id', '{{%ad_campaign}}', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('{{%ad_placement}}');
    }
}
