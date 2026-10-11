<?php

use app\enums\PaymentStatus;
use app\enums\PaymentType;
use app\models\Payment;
use app\widgets\StatusBadgeWidget;
use yii\bootstrap5\Html;
use yii\bootstrap5\LinkPager;
use yii\grid\GridView;

/** @var yii\web\View $this */
/** @var app\models\search\PaymentSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Платежи';
?>
<h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>
<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'filterModel' => $searchModel,
    'pager' => ['class' => LinkPager::class],
    'tableOptions' => ['class' => 'table table-sm table-hover align-middle'],
    'columns' => [
        'id',
        [
            'attribute' => 'created_at',
            'format' => 'datetime',
            'filter' => Html::activeInput('date', $searchModel, 'date_from', ['class' => 'form-control form-control-sm'])
                . Html::activeInput('date', $searchModel, 'date_to', ['class' => 'form-control form-control-sm mt-1']),
        ],
        ['attribute' => 'user_id', 'value' => static fn(Payment $p) => $p->user->getPrivateName()],
        ['attribute' => 'type', 'filter' => PaymentType::labels(), 'value' => static fn(Payment $p) => PaymentType::labelOf($p->type)],
        ['label' => 'Карточка', 'format' => 'raw', 'value' => static fn(Payment $p) => $p->planting_id ? Html::a('#' . $p->planting_id, ['/admin/planting/view', 'id' => $p->planting_id]) : '—'],
        ['attribute' => 'amount', 'value' => static fn(Payment $p) => Yii::$app->formatter->asCurrency($p->amount, $p->currency)],
        [
            'attribute' => 'status',
            'filter' => PaymentStatus::labels(),
            'format' => 'raw',
            'value' => static fn(Payment $p) => StatusBadgeWidget::widget(['status' => $p->status, 'enum' => PaymentStatus::class]),
        ],
        'provider',
        'paid_at:datetime',
        ['format' => 'raw', 'value' => static fn(Payment $p) => Html::a('<i class="bi bi-eye"></i>', ['view', 'id' => $p->id], ['class' => 'btn btn-sm btn-outline-secondary'])],
    ],
]) ?>
