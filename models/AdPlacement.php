<?php

namespace app\models;

use app\enums\BannerPosition;
use app\enums\PlacementType;
use app\models\query\AdPlacementQuery;
use app\validators\CoordinateValidator;
use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\Url;

/**
 * Рекламное размещение: точка на карте или баннер (ТЗ 6.12).
 *
 * @property int $id
 * @property int $campaign_id
 * @property string $type
 * @property string|float|null $latitude
 * @property string|float|null $longitude
 * @property string|null $marker_icon
 * @property string|null $image_path
 * @property string|null $image_mobile_path
 * @property string|null $position
 * @property string|null $target_url
 * @property int $priority
 * @property bool $is_active
 * @property int $impressions
 * @property int $clicks
 * @property int $created_at
 * @property int $updated_at
 *
 * @property-read AdCampaign $campaign
 */
class AdPlacement extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%ad_placement}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        $isPoint = fn() => $this->type === PlacementType::Point->value;
        $isBanner = fn() => $this->type === PlacementType::Banner->value;
        $isPointJs = "function(){ return $('#adplacement-type, #adplacementform-type').val() === 'point'; }";
        $isBannerJs = "function(){ return $('#adplacement-type, #adplacementform-type').val() === 'banner'; }";

        return [
            [['campaign_id', 'type'], 'required'],
            ['campaign_id', 'exist', 'targetClass' => AdCampaign::class, 'targetAttribute' => 'id'],
            ['type', 'in', 'range' => PlacementType::values()],
            [['latitude', 'longitude'], 'required', 'when' => $isPoint, 'whenClient' => $isPointJs,
                'message' => 'Для точки на карте координаты обязательны.'],
            ['latitude', CoordinateValidator::class, 'type' => 'latitude', 'pairAttribute' => 'longitude', 'when' => $isPoint],
            ['longitude', CoordinateValidator::class, 'type' => 'longitude', 'pairAttribute' => 'latitude', 'when' => $isPoint],
            [['image_path', 'position'], 'required', 'when' => $isBanner, 'whenClient' => $isBannerJs,
                'message' => 'Для баннера обязательны изображение и позиция.'],
            ['position', 'in', 'range' => BannerPosition::values(), 'skipOnEmpty' => true],
            [['marker_icon', 'image_path', 'image_mobile_path'], 'string', 'max' => 255],
            ['target_url', 'url', 'defaultScheme' => 'https', 'validSchemes' => ['http', 'https']],
            ['target_url', 'string', 'max' => 500],
            ['priority', 'integer'],
            ['priority', 'default', 'value' => 0],
            ['is_active', 'boolean'],
            ['is_active', 'default', 'value' => true],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'campaign_id' => 'Кампания',
            'type' => 'Тип',
            'latitude' => 'Широта',
            'longitude' => 'Долгота',
            'marker_icon' => 'Иконка маркера',
            'image_path' => 'Изображение баннера',
            'image_mobile_path' => 'Изображение для мобильных',
            'position' => 'Позиция',
            'target_url' => 'Ссылка перехода',
            'priority' => 'Приоритет',
            'is_active' => 'Активно',
            'impressions' => 'Показы',
            'clicks' => 'Клики',
            'created_at' => 'Создано',
            'updated_at' => 'Изменено',
        ];
    }

    public static function find(): AdPlacementQuery
    {
        return new AdPlacementQuery(static::class);
    }

    public function getCampaign(): ActiveQuery
    {
        return $this->hasOne(AdCampaign::class, ['id' => 'campaign_id']);
    }

    public function isPoint(): bool
    {
        return $this->type === PlacementType::Point->value;
    }

    public function isBanner(): bool
    {
        return $this->type === PlacementType::Banner->value;
    }

    /**
     * Куда ведёт размещение: target_url или карточка рекламы.
     */
    public function getTargetUrl(): string
    {
        return $this->target_url ?: Url::to(['/ad/view', 'slug' => $this->campaign->slug]);
    }

    public function getImageUrl(): ?string
    {
        return $this->image_path ? Yii::$app->get('storage')->getPublicUrl($this->image_path) : null;
    }

    public function getImageMobileUrl(): ?string
    {
        return $this->image_mobile_path ? Yii::$app->get('storage')->getPublicUrl($this->image_mobile_path) : $this->getImageUrl();
    }

    public function getMarkerIconUrl(): ?string
    {
        return $this->marker_icon ? Yii::$app->get('storage')->getPublicUrl($this->marker_icon) : null;
    }

    public function registerImpression(int $count = 1): void
    {
        $this->updateCounters(['impressions' => $count]);
    }

    public function registerClick(): void
    {
        $this->updateCounters(['clicks' => 1]);
    }

    public function getCtr(): float
    {
        return $this->impressions > 0 ? round($this->clicks / $this->impressions * 100, 2) : 0.0;
    }

    public function afterDelete(): void
    {
        parent::afterDelete();
        $storage = Yii::$app->get('storage');
        foreach ([$this->image_path, $this->image_mobile_path, $this->marker_icon] as $path) {
            if ($path) {
                $storage->deletePublic($path);
            }
        }
    }
}
