<?php

use yii\db\Migration;

/**
 * Фотографии посадок (ТЗ 5.6) + FK planting.main_photo_id.
 */
class m261011_000009_create_planting_photo_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%planting_photo}}', [
            'id' => $this->primaryKey(),
            'planting_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'file_path' => $this->string()->notNull(),
            'public_path' => $this->string()->null(),
            'thumb_path' => $this->string()->null(),
            'taken_at' => $this->date()->notNull(),
            'exif_latitude' => $this->decimal(10, 7)->null(),
            'exif_longitude' => $this->decimal(10, 7)->null(),
            'source' => $this->string(10)->notNull()->defaultValue('upload'),
            'width' => $this->integer()->notNull()->defaultValue(0),
            'height' => $this->integer()->notNull()->defaultValue(0),
            'file_size' => $this->integer()->notNull()->defaultValue(0),
            'mime_type' => $this->string(50)->notNull(),
            'status' => $this->string(20)->notNull()->defaultValue('pending'),
            'moderated_by' => $this->integer()->null(),
            'moderation_comment' => $this->text()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('ix_planting_photo_planting_status_taken', '{{%planting_photo}}', ['planting_id', 'status', 'taken_at']);
        $this->createIndex('ix_planting_photo_status', '{{%planting_photo}}', 'status');
        $this->createIndex('ix_planting_photo_user', '{{%planting_photo}}', 'user_id');

        $this->addForeignKey('fk_planting_photo_planting', '{{%planting_photo}}', 'planting_id', '{{%planting}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_planting_photo_user', '{{%planting_photo}}', 'user_id', '{{%user}}', 'id', 'RESTRICT', 'CASCADE');
        $this->addForeignKey('fk_planting_photo_moderator', '{{%planting_photo}}', 'moderated_by', '{{%user}}', 'id', 'SET NULL', 'CASCADE');

        $this->addForeignKey('fk_planting_main_photo', '{{%planting}}', 'main_photo_id', '{{%planting_photo}}', 'id', 'SET NULL', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_planting_main_photo', '{{%planting}}');
        $this->dropTable('{{%planting_photo}}');
    }
}
