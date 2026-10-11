<?php

use app\models\PlantingPhoto;
use yii\bootstrap5\Html;
use yii\bootstrap5\LinkPager;
use yii\data\ActiveDataProvider;

/** @var yii\web\View $this */
/** @var string $tab */
/** @var array $counts */
/** @var ActiveDataProvider $dataProvider */

$this->title = 'Модерация';
/** @var PlantingPhoto[] $photos */
$photos = $dataProvider->getModels();
?>
<h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>
<?= $this->render('_tabs', ['tab' => $tab, 'counts' => $counts]) ?>

<?php if (!$photos): ?>
    <p class="text-muted">Очередь фотографий пуста.</p>
<?php else: ?>
    <?= Html::beginForm(['bulk-photos'], 'post', ['id' => 'bulk-photos-form']) ?>
    <div class="d-flex flex-wrap gap-2 align-items-center mb-3 sticky-top bg-white py-2">
        <div class="form-check mb-0">
            <input type="checkbox" class="form-check-input" id="select-all" data-select-all="ids[]">
            <label for="select-all" class="form-check-label">Выбрать все</label>
        </div>
        <button type="submit" name="action" value="approve" class="btn btn-success btn-sm"><i class="bi bi-check-lg"></i> Одобрить выбранные</button>
        <input type="text" name="comment" class="form-control form-control-sm" style="max-width: 280px" placeholder="Причина отклонения">
        <button type="submit" name="action" value="reject" class="btn btn-outline-danger btn-sm"><i class="bi bi-x-lg"></i> Отклонить выбранные</button>
    </div>

    <div class="row g-3">
        <?php foreach ($photos as $photo): ?>
            <div class="col-6 col-md-3 col-xl-2">
                <label class="card h-100 photo-select">
                    <input type="checkbox" name="ids[]" value="<?= $photo->id ?>" class="form-check-input photo-select__check">
                    <img src="<?= Html::encode($photo->isProcessed() ? $photo->getThumbUrl() : $photo->getOriginalUrl()) ?>" class="card-img-top" alt="" loading="lazy">
                    <div class="card-body p-2 small">
                        <div><?= Yii::$app->formatter->asDate($photo->taken_at) ?></div>
                        <div class="text-truncate"><?= Html::a('#' . $photo->planting_id . ' ' . Html::encode($photo->planting->species?->name), ['planting', 'id' => $photo->planting_id]) ?></div>
                        <div class="text-muted"><?= Html::encode(\app\enums\PlantingStatus::labelOf($photo->planting->status)) ?></div>
                        <?= Html::a('Подробнее', ['photo', 'id' => $photo->id]) ?>
                    </div>
                </label>
            </div>
        <?php endforeach ?>
    </div>
    <?= Html::endForm() ?>
    <div class="mt-3"><?= LinkPager::widget(['pagination' => $dataProvider->pagination]) ?></div>
<?php endif ?>
