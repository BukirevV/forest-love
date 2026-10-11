<?php

use app\enums\PlacementType;
use yii\bootstrap5\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var app\models\AdPlacement $model */

$this->title = 'Размещение #' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Размещения', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>
<p>
    <?= Html::a('Редактировать', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
    <?= Html::a('<i class="bi bi-display"></i> Предпросмотр', ['preview', 'id' => $model->id], ['class' => 'btn btn-outline-secondary', 'target' => '_blank']) ?>
    <?= Html::a('Удалить', ['delete', 'id' => $model->id], ['class' => 'btn btn-outline-danger', 'data-method' => 'post', 'data-confirm' => 'Удалить размещение?']) ?>
</p>
<?= DetailView::widget(['model' => $model, 'attributes' => [
    ['attribute' => 'campaign_id', 'format' => 'raw', 'value' => Html::a(Html::encode($model->campaign->title), ['/admin/ad-campaign/view', 'id' => $model->campaign_id])],
    ['attribute' => 'type', 'value' => PlacementType::labelOf($model->type)],
    'latitude', 'longitude',
    ['attribute' => 'marker_icon', 'format' => 'raw', 'value' => $model->getMarkerIconUrl() ? Html::img($model->getMarkerIconUrl(), ['height' => 32]) : null],
    ['attribute' => 'image_path', 'format' => 'raw', 'value' => $model->getImageUrl() ? Html::img($model->getImageUrl(), ['style' => 'max-width:100%;max-height:150px']) : null],
    ['attribute' => 'position', 'value' => \app\enums\BannerPosition::labelOf($model->position)],
    ['label' => 'Переход', 'format' => 'url', 'value' => $model->getTargetUrl()],
    'priority', 'is_active:boolean', 'impressions', 'clicks',
    ['label' => 'CTR', 'value' => $model->getCtr() . '%'],
]]) ?>
