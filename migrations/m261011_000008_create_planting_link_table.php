<?php

use yii\db\Migration;

/**
 * Ссылки «Кто посадил» (ТЗ 5.5).
 */
class m261011_000008_create_planting_link_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%planting_link}}', [
            'id' => $this->primaryKey(),
            'planting_id' => $this->integer()->notNull(),
            'url' => $this->string(500)->notNull(),
            'label' => $this->string(100)->null(),
            'network' => $this->string(30)->notNull()->defaultValue('site'),
            'sort' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('ix_planting_link_planting_sort', '{{%planting_link}}', ['planting_id', 'sort']);
        $this->addForeignKey('fk_planting_link_planting', '{{%planting_link}}', 'planting_id', '{{%planting}}', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('{{%planting_link}}');
    }
}
