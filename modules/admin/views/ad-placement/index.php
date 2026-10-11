<?php

use app\enums\PlacementType;
use app\models\AdPlacement;
use yii\bootstrap5\Html;
use yii\grid\GridView;

/** @var yii\web\View $this */
/** @var app\models\search\AdPlacementSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var array $campaigns */

$this->title = 'Рекламные размещения';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><?= Html::encode($this->title) ?></h1>
    <?= Html::a('<i class="bi bi-plus-lg"></i> Добавить', ['create'], ['class' => 'btn btn-success']) ?>
</div>
<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'filterModel' => $searchModel,
    'tableOptions' => ['class' => 'table table-hover align-middle'],
    'columns' => [
        'id',
        ['attribute' => 'campaign_id', 'filter' => $campaigns, 'value' => 'campaign.title'],
        ['attribute' => 'type', 'filter' => PlacementType::labels(), 'value' => static fn(AdPlacement $p) => PlacementType::labelOf($p->type)],
        [
            'label' => 'Где',
            'format' => 'raw',
            'value' => static fn(AdPlacement $p) => $p->isPoint()
                ? sprintf('%.4f, %.4f', $p->latitude, $p->longitude)
                : ($p->getImageUrl() ? Html::img($p->getImageUrl(), ['style' => 'max-height:40px']) : '') . ' ' . Html::encode(\app\enums\BannerPosition::labelOf($p->position)),
        ],
        'priority',
        ['attribute' => 'is_active', 'format' => 'boolean', 'filter' => ['1' => 'Да', '0' => 'Нет']],
        'impressions',
        'clicks',
        [
            'format' => 'raw',
            'value' => static fn(AdPlacement $p) => Html::a('<i class="bi bi-eye"></i>', ['view', 'id' => $p->id], ['class' => 'btn btn-sm btn-outline-secondary'])
                . ' ' . Html::a('<i class="bi bi-pencil"></i>', ['update', 'id' => $p->id], ['class' => 'btn btn-sm btn-outline-secondary']),
        ],
    ],
]) ?>
