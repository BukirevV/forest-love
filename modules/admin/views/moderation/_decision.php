<?php

use app\enums\ModerationAction;
use app\forms\ModerationForm;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

/** @var ModerationForm $model */
/** @var bool $allowHide */

$allowHide ??= false;
?>
<div class="card border-primary">
    <div class="card-body">
        <h2 class="h6">Решение</h2>
        <?php $form = ActiveForm::begin(['id' => 'moderation-form']) ?>
            <?= Html::activeHiddenInput($model, 'action') ?>
            <?= $form->field($model, 'comment')->textarea(['rows' => 3, 'placeholder' => 'Комментарий (обязателен при отклонении) — будет отправлен владельцу']) ?>
            <div class="d-flex gap-2 flex-wrap">
                <?= Html::submitButton('<i class="bi bi-check-lg"></i> Одобрить', [
                    'class' => 'btn btn-success', 'onclick' => "$('#moderationform-action').val('" . ModerationAction::Approve->value . "')",
                ]) ?>
                <?= Html::submitButton('<i class="bi bi-x-lg"></i> Отклонить', [
                    'class' => 'btn btn-danger', 'onclick' => "$('#moderationform-action').val('" . ModerationAction::Reject->value . "')",
                ]) ?>
                <?php if ($allowHide): ?>
                    <?= Html::submitButton('<i class="bi bi-eye-slash"></i> Скрыть', [
                        'class' => 'btn btn-outline-dark', 'onclick' => "$('#moderationform-action').val('" . ModerationAction::Hide->value . "')",
                    ]) ?>
                <?php endif ?>
            </div>
        <?php ActiveForm::end() ?>
    </div>
</div>
