<?php

namespace app\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\caching\TagDependency;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Справочник пород (ТЗ 6.3).
 *
 * @property int $id
 * @property string $name
 * @property string|null $latin_name
 * @property string|null $description
 * @property string|null $icon
 * @property int $sort
 * @property bool $is_active
 * @property int $created_at
 * @property int $updated_at
 *
 * @property-read Planting[] $plantings
 */
class TreeSpecies extends ActiveRecord
{
    public const CACHE_TAG = 'tree_species';

    public static function tableName(): string
    {
        return '{{%tree_species}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['name', 'latin_name', 'icon'], 'trim'],
            ['name', 'required'],
            [['name', 'latin_name'], 'string', 'max' => 150],
            ['name', 'unique'],
            ['description', 'string'],
            ['icon', 'string', 'max' => 255],
            ['sort', 'integer'],
            ['sort', 'default', 'value' => 0],
            ['is_active', 'boolean'],
            ['is_active', 'default', 'value' => true],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'name' => 'Название',
            'latin_name' => 'Латинское название',
            'description' => 'Описание',
            'icon' => 'Иконка',
            'sort' => 'Сортировка',
            'is_active' => 'Активна',
            'created_at' => 'Создана',
            'updated_at' => 'Изменена',
        ];
    }

    public function getPlantings(): ActiveQuery
    {
        return $this->hasMany(Planting::class, ['species_id' => 'id']);
    }

    /**
     * [id => name] активных пород для выпадающего списка; кэш с тегом инвалидации.
     */
    public static function getList(bool $onlyActive = true): array
    {
        return Yii::$app->cache->getOrSet(
            [__METHOD__, $onlyActive],
            static function () use ($onlyActive) {
                $query = static::find()->select(['name', 'id'])->orderBy(['sort' => SORT_ASC, 'name' => SORT_ASC]);
                if ($onlyActive) {
                    $query->andWhere(['is_active' => true]);
                }
                return $query->indexBy('id')->column();
            },
            3600,
            new TagDependency(['tags' => self::CACHE_TAG])
        );
    }

    public function hasPlantings(): bool
    {
        return $this->getPlantings()->exists();
    }

    /**
     * Удаление запрещено, если есть связанные посадки — только деактивация.
     */
    public function beforeDelete(): bool
    {
        if ($this->hasPlantings()) {
            $this->addError('id', 'Порода используется в посадках — удаление запрещено, деактивируйте её.');
            return false;
        }
        return parent::beforeDelete();
    }

    public function afterSave($insert, $changedAttributes): void
    {
        parent::afterSave($insert, $changedAttributes);
        TagDependency::invalidate(Yii::$app->cache, self::CACHE_TAG);
    }

    public function afterDelete(): void
    {
        parent::afterDelete();
        TagDependency::invalidate(Yii::$app->cache, self::CACHE_TAG);
    }
}
