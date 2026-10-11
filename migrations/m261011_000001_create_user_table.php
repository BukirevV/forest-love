<?php

use yii\db\Migration;

/**
 * Пользователи (ТЗ 5.1).
 */
class m261011_000001_create_user_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%user}}', [
            'id' => $this->primaryKey(),
            'email' => $this->string(255)->null(),
            'phone' => $this->string(20)->null(),
            'password_hash' => $this->string()->null(),
            'auth_key' => $this->string(32)->notNull(),
            'display_name' => $this->string(100)->null(),
            'status' => $this->string(20)->notNull()->defaultValue('unverified'),
            'email_verified_at' => $this->integer()->null(),
            'phone_verified_at' => $this->integer()->null(),
            'last_login_at' => $this->integer()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('ux_user_email', '{{%user}}', 'email', true);
        $this->createIndex('ux_user_phone', '{{%user}}', 'phone', true);
        $this->createIndex('ix_user_status', '{{%user}}', 'status');

        // Хотя бы один контакт обязателен (PostgreSQL и MySQL 8.0.16+ поддерживают CHECK)
        $this->execute(
            'ALTER TABLE {{%user}} ADD CONSTRAINT chk_user_contact CHECK (email IS NOT NULL OR phone IS NOT NULL)'
        );
    }

    public function safeDown()
    {
        $this->dropTable('{{%user}}');
    }
}
