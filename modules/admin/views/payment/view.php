<?php

use app\enums\PaymentStatus;
use app\models\Payment;
use app\widgets\StatusBadgeWidget;
use yii\bootstrap5\Html;
use yii\helpers\Json;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var Payment $payment */

$this->title = 'Платёж №' . $payment->id;
$this->params['breadcrumbs'][] = ['label' => 'Платежи', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="d-flex align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0"><?= Html::encode($this->title) ?></h1>
    <?= StatusBadgeWidget::widget(['status' => $payment->status, 'enum' => PaymentStatus::class]) ?>
    <?php if (!$payment->isFinal() && $payment->provider_payment_id): ?>
        <?= Html::a('<i class="bi bi-arrow-repeat"></i> Сверить с провайдером', ['sync', 'id' => $payment->id], ['class' => 'btn btn-outline-primary ms-auto', 'data-method' => 'post']) ?>
    <?php endif ?>
</div>
<?= DetailView::widget(['model' => $payment, 'attributes' => [
    'id',
    ['attribute' => 'user_id', 'format' => 'raw', 'value' => Html::a(Html::encode($payment->user->getPrivateName()), ['/admin/user/view', 'id' => $payment->user_id])],
    ['attribute' => 'planting_id', 'format' => 'raw', 'value' => $payment->planting_id ? Html::a('#' . $payment->planting_id, ['/admin/planting/view', 'id' => $payment->planting_id]) : null],
    'type',
    ['attribute' => 'amount', 'value' => Yii::$app->formatter->asCurrency($payment->amount, $payment->currency)],
    'provider',
    'provider_payment_id',
    'idempotency_key',
    ['label' => 'Данные заказа', 'format' => 'raw', 'value' => Html::tag('pre', Html::encode(Json::encode($payment->getMeta(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)), ['class' => 'mb-0 small'])],
    ['attribute' => 'payload', 'format' => 'raw', 'value' => Html::tag('pre', Html::encode(Json::encode($payment->getPayloadArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)), ['class' => 'mb-0 small'])],
    'paid_at:datetime',
    'created_at:datetime',
    'updated_at:datetime',
]]) ?>
