<?php

use yii\db\Migration;

/**
 * Рекламные кампании (ТЗ 5.11).
 */
class m261011_000005_create_ad_campaign_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%ad_campaign}}', [
            'id' => $this->primaryKey(),
            'advertiser_id' => $this->integer()->notNull(),
            'title' => $this->string(200)->notNull(),
            'slug' => $this->string(200)->notNull(),
            'marketing_text' => $this->text()->notNull(),
            'legal_text' => $this->text()->null(),
            'status' => $this->string(20)->notNull()->defaultValue('draft'),
            'starts_at' => $this->integer()->null(),
            'ends_at' => $this->integer()->null(),
            'funded_trees_target' => $this->integer()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('ux_ad_campaign_slug', '{{%ad_campaign}}', 'slug', true);
        $this->createIndex('ix_ad_campaign_status_dates', '{{%ad_campaign}}', ['status', 'starts_at', 'ends_at']);
        $this->createIndex('ix_ad_campaign_advertiser', '{{%ad_campaign}}', 'advertiser_id');
        $this->addForeignKey('fk_ad_campaign_advertiser', '{{%ad_campaign}}', 'advertiser_id', '{{%advertiser}}', 'id', 'RESTRICT', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('{{%ad_campaign}}');
    }
}
