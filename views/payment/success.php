<?php

use app\enums\PaymentStatus;
use app\models\Payment;
use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var Payment $payment */

$succeeded = $payment->isSucceeded();
$this->title = $succeeded ? 'Оплата прошла' : 'Ожидаем подтверждения оплаты';
?>
<div class="text-center py-4">
    <div class="display-4 mb-3 <?= $succeeded ? 'text-success' : 'text-warning' ?>">
        <i class="bi <?= $succeeded ? 'bi-check-circle' : 'bi-hourglass-split' ?>"></i>
    </div>
    <h1 class="h3"><?= Html::encode($this->title) ?></h1>
    <?php if ($succeeded): ?>
        <p>Спасибо! Дерево стало именным.</p>
    <?php else: ?>
        <p>Платёжная система ещё не подтвердила оплату. Статус обновится автоматически — обновите страницу через минуту.</p>
        <p class="small text-muted">Статус: <?= Html::encode(PaymentStatus::labelOf($payment->status)) ?></p>
    <?php endif ?>
    <?php if ($payment->planting_id): ?>
        <?= Html::a('К карточке', ['/planting/view', 'id' => $payment->planting_id], ['class' => 'btn btn-success']) ?>
    <?php endif ?>
    <?= Html::a('История платежей', ['history'], ['class' => 'btn btn-outline-secondary']) ?>
</div>
