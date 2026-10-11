<?php

use yii\db\Migration;

/**
 * Журнал модерации (ТЗ 5.8).
 */
class m261011_000011_create_moderation_log_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%moderation_log}}', [
            'id' => $this->primaryKey(),
            'entity_type' => $this->string(30)->notNull(),
            'entity_id' => $this->integer()->notNull(),
            'moderator_id' => $this->integer()->notNull(),
            'action' => $this->string(20)->notNull(),
            'comment' => $this->text()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('ix_moderation_log_entity', '{{%moderation_log}}', ['entity_type', 'entity_id']);
        $this->createIndex('ix_moderation_log_moderator', '{{%moderation_log}}', 'moderator_id');
        $this->addForeignKey('fk_moderation_log_moderator', '{{%moderation_log}}', 'moderator_id', '{{%user}}', 'id', 'RESTRICT', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('{{%moderation_log}}');
    }
}
