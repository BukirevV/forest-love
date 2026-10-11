<?php

namespace app\models;

use app\enums\CampaignStatus;
use app\models\query\AdCampaignQuery;
use app\services\AdStatsService;
use Yii;
use yii\behaviors\SluggableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\Url;

/**
 * Рекламная кампания (ТЗ 6.11).
 *
 * @property int $id
 * @property int $advertiser_id
 * @property string $title
 * @property string $slug
 * @property string $marketing_text
 * @property string|null $legal_text
 * @property string $status
 * @property int|null $starts_at
 * @property int|null $ends_at
 * @property int|null $funded_trees_target
 * @property int $created_at
 * @property int $updated_at
 *
 * @property-read Advertiser $advertiser
 * @property-read AdPlacement[] $placements
 * @property-read Planting[] $plantings
 * @property-read Planting[] $publishedPlantings
 */
class AdCampaign extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%ad_campaign}}';
    }

    public function behaviors(): array
    {
        return [
            TimestampBehavior::class,
            [
                'class' => SluggableBehavior::class,
                'attribute' => 'title',
                'slugAttribute' => 'slug',
                'ensureUnique' => true,
                'immutable' => true,
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['title', 'slug', 'legal_text'], 'trim'],
            [['advertiser_id', 'title', 'marketing_text'], 'required'],
            ['advertiser_id', 'exist', 'targetClass' => Advertiser::class, 'targetAttribute' => 'id'],
            ['title', 'string', 'max' => 200],
            ['slug', 'string', 'max' => 200],
            ['slug', 'match', 'pattern' => '/^[a-z0-9-]+$/', 'skipOnEmpty' => true,
                'message' => 'Допустимы латинские буквы в нижнем регистре, цифры и дефис.'],
            ['slug', 'unique', 'skipOnEmpty' => true],
            [['marketing_text', 'legal_text'], 'string'],
            ['status', 'default', 'value' => CampaignStatus::Draft->value],
            ['status', 'in', 'range' => CampaignStatus::values()],
            [['starts_at', 'ends_at', 'funded_trees_target'], 'integer'],
            ['funded_trees_target', 'integer', 'min' => 1],
            ['ends_at', 'compare', 'compareAttribute' => 'starts_at', 'operator' => '>=', 'type' => 'number',
                'when' => fn() => $this->starts_at && $this->ends_at,
                'message' => 'Дата окончания должна быть не раньше даты начала.'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'advertiser_id' => 'Рекламодатель',
            'title' => 'Название',
            'slug' => 'Адрес страницы (slug)',
            'marketing_text' => 'Маркетинговый текст',
            'legal_text' => 'Маркировка рекламы / юр. информация',
            'status' => 'Статус',
            'starts_at' => 'Начало',
            'ends_at' => 'Окончание',
            'funded_trees_target' => 'План посадок',
            'created_at' => 'Создана',
            'updated_at' => 'Изменена',
        ];
    }

    public static function find(): AdCampaignQuery
    {
        return new AdCampaignQuery(static::class);
    }

    public function getAdvertiser(): ActiveQuery
    {
        return $this->hasOne(Advertiser::class, ['id' => 'advertiser_id']);
    }

    public function getPlacements(): ActiveQuery
    {
        return $this->hasMany(AdPlacement::class, ['campaign_id' => 'id']);
    }

    public function getPlantings(): ActiveQuery
    {
        return $this->hasMany(Planting::class, ['ad_campaign_id' => 'id']);
    }

    public function getPublishedPlantings(): ActiveQuery
    {
        return $this->hasMany(Planting::class, ['ad_campaign_id' => 'id'])->published();
    }

    public function getStatusEnum(): CampaignStatus
    {
        return CampaignStatus::from($this->status);
    }

    /**
     * Активна и в периоде показа.
     */
    public function isRunning(?int $now = null): bool
    {
        $now ??= time();
        return $this->status === CampaignStatus::Active->value
            && ($this->starts_at === null || $this->starts_at <= $now)
            && ($this->ends_at === null || $this->ends_at >= $now);
    }

    public function getPublicUrl(bool $scheme = false): string
    {
        return Url::to(['/ad/view', 'slug' => $this->slug], $scheme);
    }

    // ---- Агрегаты (кэшируются в AdStatsService) ----

    private function stats(): array
    {
        return Yii::$container->get(AdStatsService::class)->getStats($this);
    }

    public function getTreesCount(): int
    {
        return (int)$this->stats()['trees_count'];
    }

    /** @return array<int, array{species_id:int, name:string, count:int}> */
    public function getSpeciesBreakdown(): array
    {
        return $this->stats()['species'];
    }

    public function getFirstPlantingDate(): ?string
    {
        return $this->stats()['first_date'];
    }

    public function getLastPlantingDate(): ?string
    {
        return $this->stats()['last_date'];
    }

    /**
     * Показатель охвата: количество «ячеек» 1°×1° с посадками (до согласования методики регионов).
     */
    public function getRegionsCount(): int
    {
        return (int)$this->stats()['regions_count'];
    }

    public function afterSave($insert, $changedAttributes): void
    {
        parent::afterSave($insert, $changedAttributes);
        Yii::$container->get(AdStatsService::class)->invalidate((int)$this->id);
    }
}
