<?php

namespace app\models;

use app\services\SocialNetworkResolver;
use app\validators\UrlListValidator;
use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Ссылка «Кто посадил» (ТЗ 6.5).
 *
 * @property int $id
 * @property int $planting_id
 * @property string $url
 * @property string|null $label
 * @property string $network
 * @property int $sort
 * @property int $created_at
 * @property int $updated_at
 *
 * @property-read Planting $planting
 */
class PlantingLink extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%planting_link}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['url', 'label'], 'trim'],
            ['label', 'default', 'value' => null],
            [['planting_id', 'url'], 'required'],
            ['url', 'validateScheme'],
            ['url', 'url', 'defaultScheme' => 'https', 'validSchemes' => ['http', 'https']],
            ['url', 'string', 'max' => 500],
            ['label', 'string', 'max' => 100],
            ['network', 'string', 'max' => 30],
            ['sort', 'integer'],
            ['sort', 'default', 'value' => 0],
        ];
    }

    /**
     * Запрет javascript:, data: и подобных схем.
     */
    public function validateScheme(string $attribute): void
    {
        if (UrlListValidator::hasForbiddenScheme((string)$this->$attribute)) {
            $this->addError($attribute, 'Недопустимая схема ссылки.');
        }
    }

    public function attributeLabels(): array
    {
        return [
            'planting_id' => 'Карточка',
            'url' => 'Ссылка',
            'label' => 'Подпись',
            'network' => 'Сеть',
            'sort' => 'Порядок',
        ];
    }

    public function getPlanting(): ActiveQuery
    {
        return $this->hasOne(Planting::class, ['id' => 'planting_id']);
    }

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        if ($insert || $this->isAttributeChanged('url') || empty($this->network)) {
            $this->network = Yii::$container->get(SocialNetworkResolver::class)->resolve($this->url);
        }
        return true;
    }

    /**
     * CSS-класс иконки соцсети (Bootstrap Icons).
     */
    public function getIconClass(): string
    {
        return SocialNetworkResolver::iconClass($this->network);
    }

    public function getNetworkLabel(): string
    {
        return SocialNetworkResolver::label($this->network);
    }

    public function getDisplayLabel(): string
    {
        if ($this->label) {
            return $this->label;
        }
        $host = parse_url($this->url, PHP_URL_HOST) ?: $this->url;
        $path = trim((string)parse_url($this->url, PHP_URL_PATH), '/');
        return $path !== '' ? $host . '/' . $path : $host;
    }
}
