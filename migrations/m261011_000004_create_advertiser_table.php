<?php

use yii\db\Migration;

/**
 * Рекламодатели (ТЗ 5.10).
 */
class m261011_000004_create_advertiser_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%advertiser}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(200)->notNull(),
            'legal_name' => $this->string(300)->notNull(),
            'legal_info' => $this->text()->notNull(),
            'website_url' => $this->string(500)->null(),
            'logo_path' => $this->string()->null(),
            'contact_email' => $this->string()->null(),
            'contact_phone' => $this->string()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('ix_advertiser_name', '{{%advertiser}}', 'name');
    }

    public function safeDown()
    {
        $this->dropTable('{{%advertiser}}');
    }
}
