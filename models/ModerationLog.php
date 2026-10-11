<?php

namespace app\models;

use app\enums\ModerationAction;
use app\enums\ModerationEntity;
use Yii;
use yii\base\NotSupportedException;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Журнал модерации (ТЗ 6.8). Только создание.
 *
 * @property int $id
 * @property string $entity_type
 * @property int $entity_id
 * @property int $moderator_id
 * @property string $action
 * @property string|null $comment
 * @property int $created_at
 * @property int $updated_at
 *
 * @property-read User $moderator
 */
class ModerationLog extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%moderation_log}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['entity_type', 'entity_id', 'moderator_id', 'action'], 'required'],
            [['entity_id', 'moderator_id'], 'integer'],
            ['entity_type', 'in', 'range' => ModerationEntity::values()],
            ['action', 'in', 'range' => ModerationAction::values()],
            ['comment', 'string'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'entity_type' => 'Объект',
            'entity_id' => 'ID объекта',
            'moderator_id' => 'Модератор',
            'action' => 'Действие',
            'comment' => 'Комментарий',
            'created_at' => 'Дата',
        ];
    }

    public function getModerator(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'moderator_id']);
    }

    public static function entityTypeOf(ActiveRecord $entity): string
    {
        return match (true) {
            $entity instanceof Planting => ModerationEntity::Planting->value,
            $entity instanceof PlantingRevision => ModerationEntity::PlantingRevision->value,
            $entity instanceof PlantingPhoto => ModerationEntity::PlantingPhoto->value,
            default => throw new \InvalidArgumentException('Неизвестный тип объекта модерации: ' . $entity::class),
        };
    }

    public static function log(ActiveRecord $entity, ModerationAction|string $action, ?string $comment = null, ?int $moderatorId = null): self
    {
        $model = new self([
            'entity_type' => self::entityTypeOf($entity),
            'entity_id' => $entity->getPrimaryKey(),
            'moderator_id' => $moderatorId ?? (int)Yii::$app->user->id,
            'action' => $action instanceof ModerationAction ? $action->value : $action,
            'comment' => $comment !== '' ? $comment : null,
        ]);
        $model->save(false);
        Yii::info(sprintf('%s #%d: %s by %d', $model->entity_type, $model->entity_id, $model->action, $model->moderator_id), 'moderation');
        return $model;
    }

    public function beforeSave($insert): bool
    {
        if (!$insert) {
            throw new NotSupportedException('Записи журнала модерации не редактируются.');
        }
        return parent::beforeSave($insert);
    }

    public function beforeDelete(): bool
    {
        throw new NotSupportedException('Записи журнала модерации не удаляются.');
    }
}
