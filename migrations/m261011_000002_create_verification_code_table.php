<?php

use yii\db\Migration;

/**
 * Коды и токены подтверждения (ТЗ 5.2).
 */
class m261011_000002_create_verification_code_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%verification_code}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'channel' => $this->string(10)->notNull(),
            'purpose' => $this->string(20)->notNull(),
            'target' => $this->string(255)->notNull(),
            'code_hash' => $this->string()->notNull(),
            'attempts' => $this->smallInteger()->notNull()->defaultValue(0),
            'expires_at' => $this->integer()->notNull(),
            'used_at' => $this->integer()->null(),
            'ip' => $this->string(45)->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('ix_vc_target_purpose_expires', '{{%verification_code}}', ['target', 'purpose', 'expires_at']);
        $this->createIndex('ix_vc_user', '{{%verification_code}}', 'user_id');
        $this->addForeignKey('fk_vc_user', '{{%verification_code}}', 'user_id', '{{%user}}', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('{{%verification_code}}');
    }
}
