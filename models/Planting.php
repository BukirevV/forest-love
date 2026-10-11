<?php

namespace app\models;

use app\enums\CoordsSource;
use app\enums\NamedStatus;
use app\enums\PlantingStatus;
use app\enums\RevisionStatus;
use app\models\query\PlantingQuery;
use app\validators\CoordinateValidator;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\Url;

/**
 * Карточка посадки (ТЗ 6.4).
 *
 * @property int $id
 * @property int $user_id
 * @property int $species_id
 * @property string|float $latitude
 * @property string|float $longitude
 * @property string $coords_source
 * @property string|null $planted_at
 * @property string|null $title
 * @property string|null $description
 * @property string $status
 * @property string $named_status
 * @property int|null $named_until
 * @property int|null $ad_campaign_id
 * @property int|null $main_photo_id
 * @property int|null $approved_at
 * @property int|null $moderated_by
 * @property string|null $moderation_comment
 * @property int $created_at
 * @property int $updated_at
 *
 * @property-read User $user
 * @property-read TreeSpecies $species
 * @property-read PlantingLink[] $links
 * @property-read PlantingPhoto[] $photos
 * @property-read PlantingPhoto[] $approvedPhotos
 * @property-read PlantingPhoto|null $mainPhoto
 * @property-read AdCampaign|null $adCampaign
 * @property-read PlantingRevision|null $pendingRevision
 * @property-read Payment[] $payments
 * @property-read User|null $moderator
 * @property-read bool $isNamed
 * @property-read string $displayTitle
 */
class Planting extends ActiveRecord
{
    /** Поля, которые владелец может менять (переносятся через ревизию). */
    public const EDITABLE_FIELDS = [
        'species_id', 'latitude', 'longitude', 'coords_source', 'planted_at', 'title', 'description',
    ];

    public static function tableName(): string
    {
        return '{{%planting}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['title', 'description'], 'trim'],
            [['title', 'description', 'planted_at', 'ad_campaign_id', 'main_photo_id'], 'default', 'value' => null],
            [['user_id', 'species_id', 'latitude', 'longitude'], 'required'],
            ['species_id', 'exist', 'targetClass' => TreeSpecies::class, 'targetAttribute' => 'id'],
            ['user_id', 'exist', 'targetClass' => User::class, 'targetAttribute' => 'id'],
            ['latitude', CoordinateValidator::class, 'type' => CoordinateValidator::TYPE_LATITUDE, 'pairAttribute' => 'longitude'],
            ['longitude', CoordinateValidator::class, 'type' => CoordinateValidator::TYPE_LONGITUDE, 'pairAttribute' => 'latitude'],
            ['coords_source', 'default', 'value' => CoordsSource::Manual->value],
            ['coords_source', 'in', 'range' => CoordsSource::values()],
            ['planted_at', 'date', 'format' => 'php:Y-m-d'],
            ['planted_at', 'validateNotInFuture'],
            ['title', 'string', 'max' => 150],
            ['description', 'string', 'max' => 5000],
            ['status', 'default', 'value' => PlantingStatus::Draft->value],
            ['status', 'in', 'range' => PlantingStatus::values()],
            ['named_status', 'default', 'value' => NamedStatus::None->value],
            ['named_status', 'in', 'range' => NamedStatus::values()],
            [['named_until', 'approved_at', 'moderated_by'], 'integer'],
            ['ad_campaign_id', 'exist', 'targetClass' => AdCampaign::class, 'targetAttribute' => 'id'],
            ['main_photo_id', 'exist', 'targetClass' => PlantingPhoto::class, 'targetAttribute' => 'id'],
            ['moderation_comment', 'string'],
        ];
    }

    public function validateNotInFuture(string $attribute): void
    {
        if ($this->$attribute && strtotime((string)$this->$attribute) > strtotime('today 23:59:59')) {
            $this->addError($attribute, 'Дата посадки не может быть в будущем.');
        }
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'user_id' => 'Владелец',
            'species_id' => 'Порода',
            'latitude' => 'Широта',
            'longitude' => 'Долгота',
            'coords_source' => 'Источник координат',
            'planted_at' => 'Дата посадки',
            'title' => 'Имя дерева / подпись',
            'description' => 'Описание',
            'status' => 'Статус модерации',
            'named_status' => 'Именное размещение',
            'named_until' => 'Именное до',
            'ad_campaign_id' => 'Рекламная кампания',
            'main_photo_id' => 'Обложка',
            'approved_at' => 'Опубликована',
            'moderated_by' => 'Модератор',
            'moderation_comment' => 'Комментарий модератора',
            'created_at' => 'Создана',
            'updated_at' => 'Изменена',
        ];
    }

    public static function find(): PlantingQuery
    {
        return new PlantingQuery(static::class);
    }

    // ---- Связи ----

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getSpecies(): ActiveQuery
    {
        return $this->hasOne(TreeSpecies::class, ['id' => 'species_id']);
    }

    public function getLinks(): ActiveQuery
    {
        return $this->hasMany(PlantingLink::class, ['planting_id' => 'id'])->orderBy(['sort' => SORT_ASC, 'id' => SORT_ASC]);
    }

    public function getPhotos(): ActiveQuery
    {
        return $this->hasMany(PlantingPhoto::class, ['planting_id' => 'id'])
            ->orderBy(['taken_at' => SORT_ASC, 'id' => SORT_ASC]);
    }

    public function getApprovedPhotos(): ActiveQuery
    {
        return $this->hasMany(PlantingPhoto::class, ['planting_id' => 'id'])
            ->approved()
            ->chronological();
    }

    public function getMainPhoto(): ActiveQuery
    {
        return $this->hasOne(PlantingPhoto::class, ['id' => 'main_photo_id']);
    }

    public function getAdCampaign(): ActiveQuery
    {
        return $this->hasOne(AdCampaign::class, ['id' => 'ad_campaign_id']);
    }

    public function getPendingRevision(): ActiveQuery
    {
        return $this->hasOne(PlantingRevision::class, ['planting_id' => 'id'])
            ->andOnCondition(['status' => RevisionStatus::Pending->value]);
    }

    public function getRevisions(): ActiveQuery
    {
        return $this->hasMany(PlantingRevision::class, ['planting_id' => 'id'])->orderBy(['id' => SORT_DESC]);
    }

    public function getPayments(): ActiveQuery
    {
        return $this->hasMany(Payment::class, ['planting_id' => 'id'])->orderBy(['id' => SORT_DESC]);
    }

    public function getModerator(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'moderated_by']);
    }

    // ---- Состояние ----

    public function getStatusEnum(): PlantingStatus
    {
        return PlantingStatus::from($this->status);
    }

    public function getNamedStatusEnum(): NamedStatus
    {
        return NamedStatus::from($this->named_status);
    }

    public function isPublished(): bool
    {
        return $this->status === PlantingStatus::Approved->value;
    }

    public function isHidden(): bool
    {
        return $this->status === PlantingStatus::Hidden->value;
    }

    public function isDraft(): bool
    {
        return $this->status === PlantingStatus::Draft->value;
    }

    /**
     * Признак «именное дерево» = named_status active (ТЗ 4.4).
     */
    public function isNamed(): bool
    {
        return $this->named_status === NamedStatus::Active->value
            && ($this->named_until === null || $this->named_until > time());
    }

    public function getIsNamed(): bool
    {
        return $this->isNamed();
    }

    public function canBeEditedBy(?User $user): bool
    {
        return $user !== null
            && (int)$this->user_id === (int)$user->id
            && !$this->isHidden();
    }

    public function hasPendingChanges(): bool
    {
        return $this->getPendingRevision()->exists();
    }

    /**
     * Можно ли отправить на модерацию (есть хотя бы одна фотография).
     */
    public function canBeSubmitted(): bool
    {
        return in_array($this->status, [PlantingStatus::Draft->value, PlantingStatus::Rejected->value], true)
            && $this->getPhotos()->exists();
    }

    /**
     * Можно ли оформить именное размещение.
     */
    public function canOrderNamed(): bool
    {
        return !$this->isHidden() && !$this->isNamed();
    }

    public function getDisplayTitle(): string
    {
        if ($this->isNamed() && $this->title) {
            return $this->title;
        }
        return $this->species?->name ?? ('Дерево #' . $this->id);
    }

    public function getPublicUrl(bool $scheme = false): string
    {
        return Url::to(['/tree/view', 'id' => $this->id], $scheme);
    }

    /**
     * Компактное представление для GeoJSON (ТЗ 6.4).
     */
    public function toMapPoint(): array
    {
        return [
            'type' => 'Feature',
            'id' => (int)$this->id,
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [(float)$this->longitude, (float)$this->latitude],
            ],
            'properties' => [
                'id' => (int)$this->id,
                'named' => $this->isNamed(),
                'species_id' => (int)$this->species_id,
            ],
        ];
    }

    /**
     * Данные для публичной карточки / всплывающего окна.
     */
    public function toPublicArray(): array
    {
        $cover = $this->mainPhoto && $this->mainPhoto->isApproved()
            ? $this->mainPhoto
            : ($this->approvedPhotos[count($this->approvedPhotos) - 1] ?? null);

        return [
            'id' => (int)$this->id,
            'title' => $this->getDisplayTitle(),
            'species' => $this->species?->name,
            'species_latin' => $this->species?->latin_name,
            'latitude' => (float)$this->latitude,
            'longitude' => (float)$this->longitude,
            'planted_at' => $this->planted_at,
            'named' => $this->isNamed(),
            'description' => $this->description,
            'photo' => $cover?->getThumbUrl(),
            'photos_count' => count($this->approvedPhotos),
            'links' => array_map(static fn(PlantingLink $l) => [
                'url' => $l->url,
                'label' => $l->label,
                'network' => $l->network,
                'icon' => $l->getIconClass(),
            ], $this->links),
            'campaign' => $this->adCampaign ? [
                'title' => $this->adCampaign->title,
                'url' => Url::to(['/ad/view', 'slug' => $this->adCampaign->slug]),
            ] : null,
            'url' => $this->getPublicUrl(),
        ];
    }

    /**
     * Снимок редактируемых полей и ссылок (для ревизии/diff).
     */
    public function snapshot(): array
    {
        $data = [];
        foreach (self::EDITABLE_FIELDS as $field) {
            $data[$field] = $this->$field;
        }
        $data['links'] = array_map(
            static fn(PlantingLink $l) => ['url' => $l->url, 'label' => $l->label],
            $this->links
        );
        return $data;
    }
}
