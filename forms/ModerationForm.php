<?php

namespace app\forms;

use app\enums\ModerationAction;
use yii\base\Model;

/**
 * Решение модератора. Комментарий обязателен при отклонении.
 */
class ModerationForm extends Model
{
    public ?string $action = null;
    public ?string $comment = null;

    public function rules(): array
    {
        return [
            ['action', 'required'],
            ['action', 'in', 'range' => [ModerationAction::Approve->value, ModerationAction::Reject->value, ModerationAction::Hide->value]],
            ['comment', 'trim'],
            ['comment', 'string', 'max' => 2000],
            ['comment', 'required', 'when' => fn() => $this->action === ModerationAction::Reject->value,
                'whenClient' => "function(){ return $('#moderationform-action').val() === 'reject'; }",
                'message' => 'Укажите причину отклонения — она будет отправлена владельцу.'],
        ];
    }

    public function attributeLabels(): array
    {
        return ['action' => 'Решение', 'comment' => 'Комментарий'];
    }

    public function isApprove(): bool
    {
        return $this->action === ModerationAction::Approve->value;
    }
}
