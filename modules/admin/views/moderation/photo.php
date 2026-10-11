<?php

use app\enums\PhotoStatus;
use app\forms\ModerationForm;
use app\models\PlantingPhoto;
use app\widgets\StatusBadgeWidget;
use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var PlantingPhoto $photo */
/** @var ModerationForm $model */

$this->title = 'Фото #' . $photo->id;
$this->params['breadcrumbs'][] = ['label' => 'Модерация', 'url' => ['index', 'tab' => 'photos']];
$this->params['breadcrumbs'][] = $this->title;
?>
<h1 class="h3 mb-3"><?= Html::encode($this->title) ?> <?= StatusBadgeWidget::widget(['status' => $photo->status, 'enum' => PhotoStatus::class]) ?></h1>
<div class="row g-4">
    <div class="col-lg-8">
        <img src="<?= Html::encode($photo->isProcessed() ? $photo->getPublicUrl() : $photo->getOriginalUrl()) ?>" class="img-fluid rounded" alt="">
    </div>
    <div class="col-lg-4">
        <dl>
            <dt>Карточка</dt><dd><?= Html::a('#' . $photo->planting_id . ' ' . Html::encode($photo->planting->getDisplayTitle()), ['planting', 'id' => $photo->planting_id]) ?></dd>
            <dt>Дата снимка</dt><dd><?= Yii::$app->formatter->asDate($photo->taken_at) ?></dd>
            <dt>Источник</dt><dd><?= Html::encode(\app\enums\PhotoSource::labelOf($photo->source)) ?></dd>
            <dt>Размер</dt><dd><?= $photo->width ?>×<?= $photo->height ?>, <?= Yii::$app->formatter->asShortSize($photo->file_size) ?></dd>
            <dt>Оригинал</dt><dd><?= Html::a('Открыть', $photo->getOriginalUrl(), ['target' => '_blank']) ?></dd>
        </dl>
        <?php if ($photo->isPending()): ?>
            <?= $this->render('_decision', ['model' => $model]) ?>
        <?php endif ?>
    </div>
</div>
