<?php

use app\enums\PaymentStatus;
use app\enums\PaymentType;
use app\models\Payment;
use app\widgets\StatusBadgeWidget;
use yii\bootstrap5\Html;
use yii\bootstrap5\LinkPager;
use yii\data\ActiveDataProvider;
use yii\grid\GridView;

/** @var yii\web\View $this */
/** @var ActiveDataProvider $dataProvider */

$this->title = 'Платежи';
?>
<h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>

<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'tableOptions' => ['class' => 'table table-hover align-middle'],
    'pager' => ['class' => LinkPager::class],
    'emptyText' => 'Платежей пока не было.',
    'columns' => [
        'id',
        'created_at:datetime',
        [
            'attribute' => 'type',
            'value' => static fn(Payment $p) => PaymentType::labelOf($p->type),
        ],
        [
            'label' => 'Дерево',
            'format' => 'raw',
            'value' => static fn(Payment $p) => $p->planting
                ? Html::a(Html::encode($p->planting->getDisplayTitle()), ['/planting/view', 'id' => $p->planting_id]) : '—',
        ],
        [
            'attribute' => 'amount',
            'value' => static fn(Payment $p) => Yii::$app->formatter->asCurrency($p->amount, $p->currency),
        ],
        [
            'attribute' => 'status',
            'format' => 'raw',
            'value' => static fn(Payment $p) => StatusBadgeWidget::widget(['status' => $p->status, 'enum' => PaymentStatus::class]),
        ],
    ],
]) ?>
