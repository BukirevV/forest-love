<?php

use app\enums\NamedStatus;
use app\enums\PlantingStatus;
use app\models\Planting;
use app\models\search\PlantingSearch;
use app\models\TreeSpecies;
use app\widgets\StatusBadgeWidget;
use yii\bootstrap5\Html;
use yii\bootstrap5\LinkPager;
use yii\grid\GridView;

/** @var yii\web\View $this */
/** @var PlantingSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var array $campaigns */

$this->title = 'Посадки';
?>
<h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>

<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'filterModel' => $searchModel,
    'pager' => ['class' => LinkPager::class],
    'tableOptions' => ['class' => 'table table-sm table-hover align-middle'],
    'columns' => [
        ['attribute' => 'id', 'headerOptions' => ['style' => 'width: 80px']],
        [
            'attribute' => 'species_id',
            'filter' => TreeSpecies::getList(false),
            'value' => 'species.name',
        ],
        [
            'attribute' => 'user_id',
            'value' => static fn(Planting $p) => $p->user->getPrivateName(),
        ],
        [
            'attribute' => 'status',
            'filter' => PlantingStatus::labels(),
            'format' => 'raw',
            'value' => static fn(Planting $p) => StatusBadgeWidget::widget(['status' => $p->status, 'enum' => PlantingStatus::class]),
        ],
        [
            'attribute' => 'named_status',
            'filter' => NamedStatus::labels(),
            'format' => 'raw',
            'value' => static fn(Planting $p) => StatusBadgeWidget::widget(['status' => $p->named_status, 'enum' => NamedStatus::class]),
        ],
        [
            'attribute' => 'ad_campaign_id',
            'filter' => $campaigns,
            'value' => static fn(Planting $p) => $p->adCampaign?->title,
        ],
        [
            'attribute' => 'has_pending_changes',
            'filter' => ['1' => 'Да', '0' => 'Нет'],
            'format' => 'raw',
            'value' => static fn(Planting $p) => $p->hasPendingChanges() ? '<span class="badge text-bg-info">есть</span>' : '',
        ],
        [
            'attribute' => 'planted_at',
            'format' => 'date',
            'filter' => Html::activeInput('date', $searchModel, 'planted_from', ['class' => 'form-control form-control-sm'])
                . Html::activeInput('date', $searchModel, 'planted_to', ['class' => 'form-control form-control-sm mt-1']),
        ],
        [
            'format' => 'raw',
            'value' => static fn(Planting $p) => Html::a('<i class="bi bi-eye"></i>', ['view', 'id' => $p->id], ['class' => 'btn btn-sm btn-outline-secondary']),
        ],
    ],
]) ?>
