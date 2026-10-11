<?php

use yii\db\Migration;

/**
 * Ревизии карточек на повторную модерацию (ТЗ 5.7).
 */
class m261011_000010_create_planting_revision_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%planting_revision}}', [
            'id' => $this->primaryKey(),
            'planting_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'data' => $this->json()->notNull(),
            'status' => $this->string(20)->notNull()->defaultValue('pending'),
            'moderated_by' => $this->integer()->null(),
            'moderation_comment' => $this->text()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('ix_planting_revision_planting_status', '{{%planting_revision}}', ['planting_id', 'status']);
        $this->createIndex('ix_planting_revision_status', '{{%planting_revision}}', 'status');

        $this->addForeignKey('fk_planting_revision_planting', '{{%planting_revision}}', 'planting_id', '{{%planting}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_planting_revision_user', '{{%planting_revision}}', 'user_id', '{{%user}}', 'id', 'RESTRICT', 'CASCADE');
        $this->addForeignKey('fk_planting_revision_moderator', '{{%planting_revision}}', 'moderated_by', '{{%user}}', 'id', 'SET NULL', 'CASCADE');

        // PostgreSQL: не более одной ревизии pending на карточку
        if ($this->db->driverName === 'pgsql') {
            $this->execute(
                "CREATE UNIQUE INDEX ux_planting_revision_one_pending ON {{%planting_revision}} (planting_id) WHERE status = 'pending'"
            );
        }
    }

    public function safeDown()
    {
        $this->dropTable('{{%planting_revision}}');
    }
}
