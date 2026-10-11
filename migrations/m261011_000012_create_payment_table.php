<?php

use yii\db\Migration;

/**
 * Платежи (ТЗ 5.9).
 */
class m261011_000012_create_payment_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%payment}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'planting_id' => $this->integer()->null(),
            'type' => $this->string(30)->notNull(),
            'amount' => $this->decimal(12, 2)->notNull(),
            'currency' => $this->char(3)->notNull(),
            'status' => $this->string(20)->notNull()->defaultValue('new'),
            'provider' => $this->string(30)->notNull(),
            'provider_payment_id' => $this->string(100)->null(),
            'idempotency_key' => $this->string(64)->notNull(),
            'payload' => $this->json()->null(),
            'meta' => $this->json()->null(),
            'paid_at' => $this->integer()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('ux_payment_provider_id', '{{%payment}}', 'provider_payment_id', true);
        $this->createIndex('ux_payment_idempotency', '{{%payment}}', 'idempotency_key', true);
        $this->createIndex('ix_payment_user', '{{%payment}}', 'user_id');
        $this->createIndex('ix_payment_planting', '{{%payment}}', 'planting_id');
        $this->createIndex('ix_payment_status', '{{%payment}}', ['status', 'created_at']);

        $this->addForeignKey('fk_payment_user', '{{%payment}}', 'user_id', '{{%user}}', 'id', 'RESTRICT', 'CASCADE');
        $this->addForeignKey('fk_payment_planting', '{{%payment}}', 'planting_id', '{{%planting}}', 'id', 'SET NULL', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('{{%payment}}');
    }
}
