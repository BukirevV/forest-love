<?php

use yii\db\Migration;

/**
 * Настройки, редактируемые администратором (цена/срок именного размещения, лимиты).
 * Значения из БД переопределяют значения из config/params.php.
 */
class m261011_000013_create_setting_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%setting}}', [
            'id' => $this->primaryKey(),
            'key' => $this->string(100)->notNull(),
            'value' => $this->text()->null(),
            'type' => $this->string(20)->notNull()->defaultValue('string'),
            'label' => $this->string(200)->notNull(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);
        $this->createIndex('ux_setting_key', '{{%setting}}', 'key', true);

        $now = time();
        $this->batchInsert('{{%setting}}', ['key', 'value', 'type', 'label', 'created_at', 'updated_at'], [
            ['named.price', '0', 'float', 'Стоимость именного размещения', $now, $now],
            ['named.currency', 'RUB', 'string', 'Валюта', $now, $now],
            ['named.durationDays', '', 'int', 'Срок именного размещения, дней (пусто — бессрочно)', $now, $now],
            ['upload.maxFileSize', (string)(15 * 1024 * 1024), 'int', 'Максимальный размер фото, байт', $now, $now],
            ['upload.maxPhotosPerPlanting', '100', 'int', 'Максимум фото в карточке', $now, $now],
            ['links.maxCount', '50', 'int', 'Максимум ссылок «Кто посадил»', $now, $now],
        ]);
    }

    public function safeDown()
    {
        $this->dropTable('{{%setting}}');
    }
}
