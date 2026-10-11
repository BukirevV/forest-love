<?php

namespace app\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\caching\TagDependency;
use yii\db\ActiveRecord;

/**
 * Настройка, редактируемая администратором. Значение из БД переопределяет params.
 *
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property string $type  string | int | float | bool
 * @property string $label
 * @property int $created_at
 * @property int $updated_at
 */
class Setting extends ActiveRecord
{
    public const CACHE_TAG = 'settings';

    public static function tableName(): string
    {
        return '{{%setting}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['key', 'label', 'type'], 'required'],
            ['key', 'string', 'max' => 100],
            ['key', 'unique'],
            ['label', 'string', 'max' => 200],
            ['type', 'in', 'range' => ['string', 'int', 'float', 'bool']],
            ['value', 'string'],
            ['value', 'number', 'when' => fn() => in_array($this->type, ['int', 'float'], true) && $this->value !== ''],
        ];
    }

    public function attributeLabels(): array
    {
        return ['key' => 'Ключ', 'value' => 'Значение', 'type' => 'Тип', 'label' => 'Описание'];
    }

    /**
     * Значение настройки: БД → params → $default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();
        if (array_key_exists($key, $all)) {
            return $all[$key];
        }
        return Yii::$app->params[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        try {
            return Yii::$app->cache->getOrSet(__METHOD__, static function () {
                $result = [];
                foreach (self::find()->all() as $row) {
                    $result[$row->key] = $row->typedValue();
                }
                return $result;
            }, 3600, new TagDependency(['tags' => self::CACHE_TAG]));
        } catch (\yii\db\Exception) {
            return []; // таблица ещё не создана
        }
    }

    public function typedValue(): mixed
    {
        if ($this->value === null || $this->value === '') {
            return null;
        }
        return match ($this->type) {
            'int' => (int)$this->value,
            'float' => (float)$this->value,
            'bool' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            default => $this->value,
        };
    }

    public function afterSave($insert, $changedAttributes): void
    {
        parent::afterSave($insert, $changedAttributes);
        TagDependency::invalidate(Yii::$app->cache, self::CACHE_TAG);
    }
}
