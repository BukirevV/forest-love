<?php

use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var string $email */

$this->title = 'Подтвердите email';
?>
<div class="text-center">
    <div class="display-5 text-success mb-2"><i class="bi bi-envelope-check"></i></div>
    <h1 class="h4"><?= Html::encode($this->title) ?></h1>
    <p>Мы отправили письмо на <strong><?= Html::encode($email) ?></strong>. Перейдите по ссылке из письма, чтобы завершить регистрацию.</p>
    <p class="small text-muted">Письмо не пришло? Проверьте папку «Спам» или отправьте повторно.</p>
    <?= Html::beginForm(['resend-code'], 'post') ?>
        <?= Html::hiddenInput('target', $email) ?>
        <?= Html::hiddenInput('purpose', 'signup') ?>
        <?= Html::submitButton('Отправить письмо ещё раз', ['class' => 'btn btn-outline-success btn-sm']) ?>
    <?= Html::endForm() ?>
</div>
