<?php

use app\enums\CampaignStatus;
use app\models\AdCampaign;
use app\widgets\StatusBadgeWidget;
use yii\bootstrap5\Html;
use yii\grid\GridView;

/** @var yii\web\View $this */
/** @var app\models\search\AdCampaignSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var array $advertisers */

$this->title = 'Рекламные кампании';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><?= Html::encode($this->title) ?></h1>
    <?= Html::a('<i class="bi bi-plus-lg"></i> Новая кампания', ['create'], ['class' => 'btn btn-success']) ?>
</div>
<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'filterModel' => $searchModel,
    'tableOptions' => ['class' => 'table table-hover align-middle'],
    'columns' => [
        'id',
        ['attribute' => 'title', 'format' => 'raw', 'value' => static fn(AdCampaign $c) => Html::a(Html::encode($c->title), ['view', 'id' => $c->id])],
        ['attribute' => 'advertiser_id', 'filter' => $advertisers, 'value' => 'advertiser.name'],
        [
            'attribute' => 'status',
            'filter' => CampaignStatus::labels(),
            'format' => 'raw',
            'value' => static fn(AdCampaign $c) => StatusBadgeWidget::widget(['status' => $c->status, 'enum' => CampaignStatus::class])
                . ($c->isRunning() ? ' <i class="bi bi-broadcast text-success" title="Показывается"></i>' : ''),
        ],
        [
            'label' => 'Период',
            'filter' => Html::activeInput('date', $searchModel, 'date_from', ['class' => 'form-control form-control-sm'])
                . Html::activeInput('date', $searchModel, 'date_to', ['class' => 'form-control form-control-sm mt-1']),
            'value' => static fn(AdCampaign $c) => ($c->starts_at ? Yii::$app->formatter->asDate($c->starts_at) : '…')
                . ' — ' . ($c->ends_at ? Yii::$app->formatter->asDate($c->ends_at) : '…'),
        ],
        ['label' => 'Деревьев', 'value' => static fn(AdCampaign $c) => $c->getTreesCount()],
    ],
]) ?>
