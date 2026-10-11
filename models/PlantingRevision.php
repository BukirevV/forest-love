<?php

namespace app\models;

use app\enums\RevisionStatus;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\Json;

/**
 * Изменения карточки на повторную модерацию (ТЗ 6.7).
 *
 * @property int $id
 * @property int $planting_id
 * @property int $user_id
 * @property array|string $data
 * @property string $status
 * @property int|null $moderated_by
 * @property string|null $moderation_comment
 * @property int $created_at
 * @property int $updated_at
 *
 * @property-read Planting $planting
 * @property-read User $user
 * @property-read User|null $moderator
 */
class PlantingRevision extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%planting_revision}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['planting_id', 'user_id', 'data'], 'required'],
            [['planting_id', 'user_id', 'moderated_by'], 'integer'],
            ['status', 'default', 'value' => RevisionStatus::Pending->value],
            ['status', 'in', 'range' => RevisionStatus::values()],
            ['moderation_comment', 'string'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'planting_id' => 'Карточка',
            'user_id' => 'Автор',
            'data' => 'Изменения',
            'status' => 'Статус',
            'moderated_by' => 'Модератор',
            'moderation_comment' => 'Комментарий модератора',
            'created_at' => 'Создана',
        ];
    }

    public function getPlanting(): ActiveQuery
    {
        return $this->hasOne(Planting::class, ['id' => 'planting_id']);
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getModerator(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'moderated_by']);
    }

    public function isPending(): bool
    {
        return $this->status === RevisionStatus::Pending->value;
    }

    public function getData(): array
    {
        $data = $this->getAttribute('data');
        if (is_string($data)) {
            $data = Json::decode($data);
        }
        return is_array($data) ? $data : [];
    }

    public function setData(array $data): void
    {
        $this->setAttribute('data', $data);
    }

    /**
     * Перенос значений ревизии в карточку, включая пересоздание ссылок.
     * Вызывается внутри транзакции (ModerationService).
     */
    public function applyTo(Planting $planting): void
    {
        $data = $this->getData();
        foreach (Planting::EDITABLE_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $planting->$field = $data[$field];
            }
        }
        if (!$planting->save()) {
            throw new \RuntimeException('Не удалось применить изменения: ' . implode('; ', $planting->getFirstErrors()));
        }

        if (array_key_exists('links', $data)) {
            PlantingLink::deleteAll(['planting_id' => $planting->id]);
            foreach (array_values((array)$data['links']) as $i => $item) {
                $link = new PlantingLink([
                    'planting_id' => $planting->id,
                    'url' => $item['url'] ?? '',
                    'label' => $item['label'] ?? null,
                    'sort' => $i,
                ]);
                if (!$link->save()) {
                    throw new \RuntimeException('Некорректная ссылка: ' . implode('; ', $link->getFirstErrors()));
                }
            }
            $planting->populateRelation('links', $planting->getLinks()->all());
        }
    }

    /**
     * Разница с текущей опубликованной версией (для экрана модератора).
     *
     * @return array<string, array{label: string, old: mixed, new: mixed}>
     */
    public function getDiff(): array
    {
        $planting = $this->planting;
        $current = $planting->snapshot();
        $new = $this->getData();
        $labels = $planting->attributeLabels();
        $labels['links'] = 'Кто посадил';

        $diff = [];
        foreach ($new as $field => $value) {
            $old = $current[$field] ?? null;
            if ($field === 'species_id') {
                $old = $planting->species?->name;
                $value = TreeSpecies::findOne($value)?->name ?? $value;
            }
            if (in_array($field, ['latitude', 'longitude'], true)) {
                $changed = abs((float)$old - (float)$value) > 1e-7;
            } else {
                $changed = self::normalize($old) !== self::normalize($value);
            }
            if ($changed) {
                $diff[$field] = ['label' => $labels[$field] ?? $field, 'old' => $old, 'new' => $value];
            }
        }
        return $diff;
    }

    private static function normalize(mixed $value): string
    {
        if (is_array($value)) {
            return Json::encode($value);
        }
        return trim((string)$value);
    }
}
