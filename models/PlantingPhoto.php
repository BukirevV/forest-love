<?php

namespace app\models;

use app\components\storage\StorageInterface;
use app\enums\PhotoSource;
use app\enums\PhotoStatus;
use app\models\query\PlantingPhotoQuery;
use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\Url;

/**
 * Фотография посадки (ТЗ 6.6).
 *
 * @property int $id
 * @property int $planting_id
 * @property int $user_id
 * @property string $file_path      путь оригинала в закрытом хранилище
 * @property string|null $public_path обработанная копия без EXIF
 * @property string|null $thumb_path
 * @property string $taken_at
 * @property string|float|null $exif_latitude
 * @property string|float|null $exif_longitude
 * @property string $source
 * @property int $width
 * @property int $height
 * @property int $file_size
 * @property string $mime_type
 * @property string $status
 * @property int|null $moderated_by
 * @property string|null $moderation_comment
 * @property int $created_at
 * @property int $updated_at
 *
 * @property-read Planting $planting
 * @property-read User $user
 */
class PlantingPhoto extends ActiveRecord
{
    public const PLACEHOLDER = '/img/photo-placeholder.svg';

    public static function tableName(): string
    {
        return '{{%planting_photo}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['planting_id', 'user_id', 'file_path', 'taken_at', 'mime_type'], 'required'],
            [['planting_id', 'user_id', 'width', 'height', 'file_size', 'moderated_by'], 'integer'],
            [['file_path', 'public_path', 'thumb_path'], 'string', 'max' => 255],
            ['taken_at', 'date', 'format' => 'php:Y-m-d'],
            [['exif_latitude', 'exif_longitude'], 'number'],
            ['source', 'in', 'range' => PhotoSource::values()],
            ['mime_type', 'string', 'max' => 50],
            ['status', 'default', 'value' => PhotoStatus::Pending->value],
            ['status', 'in', 'range' => PhotoStatus::values()],
            ['moderation_comment', 'string'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'planting_id' => 'Карточка',
            'user_id' => 'Загрузил',
            'file_path' => 'Оригинал',
            'public_path' => 'Публичная копия',
            'thumb_path' => 'Превью',
            'taken_at' => 'Дата снимка',
            'exif_latitude' => 'Широта (EXIF)',
            'exif_longitude' => 'Долгота (EXIF)',
            'source' => 'Источник',
            'width' => 'Ширина',
            'height' => 'Высота',
            'file_size' => 'Размер',
            'mime_type' => 'Тип',
            'status' => 'Статус',
            'moderated_by' => 'Модератор',
            'moderation_comment' => 'Комментарий модератора',
            'created_at' => 'Загружена',
        ];
    }

    public static function find(): PlantingPhotoQuery
    {
        return new PlantingPhotoQuery(static::class);
    }

    public function getPlanting(): ActiveQuery
    {
        return $this->hasOne(Planting::class, ['id' => 'planting_id']);
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getStatusEnum(): PhotoStatus
    {
        return PhotoStatus::from($this->status);
    }

    public function isApproved(): bool
    {
        return $this->status === PhotoStatus::Approved->value;
    }

    public function isPending(): bool
    {
        return $this->status === PhotoStatus::Pending->value;
    }

    public function isProcessed(): bool
    {
        return $this->public_path !== null;
    }

    private static function storage(): StorageInterface
    {
        return Yii::$app->get('storage');
    }

    public function getPublicUrl(): string
    {
        return $this->public_path ? self::storage()->getPublicUrl($this->public_path) : Url::to(self::PLACEHOLDER);
    }

    public function getThumbUrl(): string
    {
        return $this->thumb_path ? self::storage()->getPublicUrl($this->thumb_path) : $this->getPublicUrl();
    }

    /**
     * URL оригинала — только для модераторов (отдаётся контроллером админки).
     */
    public function getOriginalUrl(): string
    {
        return Url::to(['/admin/moderation/original', 'id' => $this->id]);
    }

    public function afterDelete(): void
    {
        parent::afterDelete();
        $storage = self::storage();
        $storage->deletePrivate($this->file_path);
        foreach ([$this->public_path, $this->thumb_path] as $path) {
            if ($path) {
                $storage->deletePublic($path);
            }
        }
    }
}
