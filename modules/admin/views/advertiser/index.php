<?php

use app\models\Advertiser;
use yii\bootstrap5\Html;
use yii\grid\GridView;

/** @var yii\web\View $this */
/** @var app\models\search\AdvertiserSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Рекламодатели';
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
        ['label' => 'Лого', 'format' => 'raw', 'value' => static fn(Advertiser $a) => $a->getLogoUrl() ? Html::img($a->getLogoUrl(), ['style' => 'max-height:32px']) : ''],
        ['attribute' => 'name', 'format' => 'raw', 'value' => static fn(Advertiser $a) => Html::a(Html::encode($a->name), ['view', 'id' => $a->id])],
        'legal_name',
        'website_url:url',
        ['label' => 'Кампаний', 'value' => static fn(Advertiser $a) => $a->getCampaigns()->count()],
    ],
]) ?>
