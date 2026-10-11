<?php

use app\enums\PaymentStatus;
use app\models\Payment;
use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var Payment $payment */

$this->title = 'Оплата не прошла';
?>
<div class="text-center py-4">
    <div class="display-4 mb-3 text-danger"><i class="bi bi-x-circle"></i></div>
    <h1 class="h3"><?= Html::encode($this->title) ?></h1>
    <p>Статус платежа: <?= Html::encode(PaymentStatus::labelOf($payment->status)) ?>. Деньги не списаны или будут возвращены платёжной системой.</p>
    <?php if ($payment->planting_id): ?>
        <?= Html::a('Попробовать снова', ['named', 'id' => $payment->planting_id], ['class' => 'btn btn-warning']) ?>
    <?php endif ?>
    <?= Html::a('История платежей', ['history'], ['class' => 'btn btn-outline-secondary']) ?>
</div>
