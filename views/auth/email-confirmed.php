<?php

use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var bool $success */
/** @var string $message */

$this->title = $success ? 'Email подтверждён' : 'Ошибка подтверждения';
?>
<div class="text-center">
    <div class="display-5 mb-2 <?= $success ? 'text-success' : 'text-danger' ?>">
        <i class="bi <?= $success ? 'bi-check-circle' : 'bi-x-circle' ?>"></i>
    </div>
    <h1 class="h4"><?= Html::encode($this->title) ?></h1>
    <p><?= Html::encode($message) ?></p>
    <?php if ($success): ?>
        <?= Html::a('Посадить первое дерево', ['/planting/create'], ['class' => 'btn btn-success']) ?>
        <?= Html::a('В кабинет', ['/cabinet/index'], ['class' => 'btn btn-outline-secondary']) ?>
    <?php else: ?>
        <?= Html::a('Войти', ['/auth/login'], ['class' => 'btn btn-success']) ?>
    <?php endif ?>
</div>
