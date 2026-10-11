<?php

use app\models\Planting;
use yii\bootstrap5\Html;

/** @var Planting $model */

$photo = $model->mainPhoto?->isApproved() ? $model->mainPhoto : null;
?>
<a href="<?= $model->getPublicUrl() ?>" class="card h-100 text-decoration-none text-reset planting-card">
    <?php if ($photo): ?>
        <img src="<?= Html::encode($photo->getThumbUrl()) ?>" class="card-img-top" alt="" loading="lazy">
    <?php else: ?>
        <div class="card-img-top planting-card__placeholder"><i class="bi bi-tree"></i></div>
    <?php endif ?>
    <div class="card-body p-2">
        <div class="fw-semibold small"><?= Html::encode($model->getDisplayTitle()) ?></div>
        <?php if ($model->planted_at): ?>
            <div class="small text-muted"><?= Yii::$app->formatter->asDate($model->planted_at) ?></div>
        <?php endif ?>
    </div>
</a>
