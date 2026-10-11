<?php

use app\widgets\BannerWidget;
use app\widgets\MapWidget;

/** @var yii\web\View $this */
/** @var app\models\AdPlacement $model */

$this->title = 'Предпросмотр размещения #' . $model->id;
$banner = $model->isBanner() ? [[
    'id' => (int)$model->id,
    'position' => $model->position,
    'image' => $model->getImageUrl(),
    'imageMobile' => $model->getImageMobileUrl(),
    'url' => '#',
    'external' => false,
    'title' => $model->campaign->title,
    'legal' => $model->campaign->legal_text,
]] : [];
?>
<div class="map-page">
    <?= MapWidget::widget([
        'mode' => MapWidget::MODE_PUBLIC,
        'center' => $model->isPoint() ? [(float)$model->latitude, (float)$model->longitude] : null,
        'zoom' => $model->isPoint() ? 12 : null,
        'options' => ['class' => 'map-page__map'],
        'clientOptions' => ['preview' => true],
    ]) ?>
    <?php if ($banner): ?>
        <?= BannerWidget::widget(['banners' => $banner]) ?>
    <?php endif ?>
    <div class="alert alert-warning position-absolute bottom-0 start-0 m-3" style="z-index:1000">
        Режим предпросмотра<?= $model->isPoint() ? ' — точка отображается, если размещение и кампания активны' : '' ?>.
    </div>
</div>
