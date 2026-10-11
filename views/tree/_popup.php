<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var array $tree результат Planting::toPublicArray() */
?>
<div class="tree-popup">
    <?php if ($tree['photo']): ?>
        <img src="<?= Html::encode($tree['photo']) ?>" alt="" class="tree-popup__photo img-fluid rounded mb-2" loading="lazy">
    <?php endif ?>
    <div class="d-flex align-items-center gap-2 mb-1">
        <strong><?= Html::encode($tree['title']) ?></strong>
        <?php if ($tree['named']): ?><span class="badge text-bg-warning">Именное</span><?php endif ?>
    </div>
    <?php if ($tree['title'] !== $tree['species']): ?>
        <div class="small"><?= Html::encode($tree['species']) ?></div>
    <?php endif ?>
    <?php if ($tree['planted_at']): ?>
        <div class="small text-muted">Посажено <?= Yii::$app->formatter->asDate($tree['planted_at'], 'long') ?></div>
    <?php endif ?>
    <?php if ($tree['links']): ?>
        <div class="small mt-1">
            <?php foreach (array_slice($tree['links'], 0, 3) as $link): ?>
                <a href="<?= Html::encode($link['url']) ?>" rel="nofollow noopener ugc" target="_blank" class="me-2">
                    <i class="<?= Html::encode($link['icon']) ?>"></i> <?= Html::encode($link['label'] ?: parse_url($link['url'], PHP_URL_HOST)) ?>
                </a>
            <?php endforeach ?>
            <?php if (count($tree['links']) > 3): ?><span class="text-muted">и ещё <?= count($tree['links']) - 3 ?></span><?php endif ?>
        </div>
    <?php endif ?>
    <?php if ($tree['campaign']): ?>
        <div class="small mt-1">Профинансировано: <?= Html::a(Html::encode($tree['campaign']['title']), $tree['campaign']['url']) ?></div>
    <?php endif ?>
    <?= Html::a('Подробнее <i class="bi bi-arrow-right"></i>', $tree['url'], ['class' => 'btn btn-sm btn-success mt-2']) ?>
</div>
