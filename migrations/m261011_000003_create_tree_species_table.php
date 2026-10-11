<?php

use yii\db\Migration;

/**
 * Справочник пород (ТЗ 5.3).
 */
class m261011_000003_create_tree_species_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%tree_species}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(150)->notNull(),
            'latin_name' => $this->string(150)->null(),
            'description' => $this->text()->null(),
            'icon' => $this->string()->null(),
            'sort' => $this->integer()->notNull()->defaultValue(0),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('ux_tree_species_name', '{{%tree_species}}', 'name', true);
        $this->createIndex('ix_tree_species_active_sort', '{{%tree_species}}', ['is_active', 'sort']);
    }

    public function safeDown()
    {
        $this->dropTable('{{%tree_species}}');
    }
}
