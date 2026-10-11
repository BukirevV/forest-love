<?php

namespace app\widgets;

use yii\base\Widget;
use yii\helpers\Html;

/**
 * Бейдж статуса модерации/оплаты по enum.
 *
 * StatusBadgeWidget::widget(['status' => $model->status, 'enum' => PlantingStatus::class])
 */
class StatusBadgeWidget extends Widget
{
    public \BackedEnum|string|null $status = null;
    /** @var class-string<\BackedEnum>|null */
    public ?string $enum = null;
    public array $options = [];

    public function run(): string
    {
        $status = $this->status;
        if (is_string($status) && $this->enum) {
            $status = ($this->enum)::tryFrom($status);
        }
        if (!$status instanceof \BackedEnum) {
            return Html::tag('span', Html::encode((string)$this->status), ['class' => 'badge text-bg-light']);
        }
        $options = $this->options;
        Html::addCssClass($options, ['badge', 'text-bg-' . $status->cssClass()]);
        return Html::tag('span', Html::encode($status->label()), $options);
    }
}
