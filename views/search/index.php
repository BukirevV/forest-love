<?php

use app\models\Planting;
use yii\bootstrap5\Html;
use yii\bootstrap5\LinkPager;

/** @var yii\web\View $this */
/** @var string $q */
/** @var yii\data\ActiveDataProvider|null $dataProvider */
/** @var bool $tooShort */

$this->title = $q !== '' ? 'Поиск: ' . $q : 'Поиск деревьев';
$this->params['breadcrumbs'][] = 'Поиск';
?>
<div class="search-page">
    <h1 class="h3 mb-3">Поиск деревьев</h1>

    <form action="<?= Html::encode(\yii\helpers\Url::to(['/search/index'])) ?>" method="get" class="mb-4" role="search">
        <div class="input-group input-group-lg">
            <input type="search" name="q" value="<?= Html::encode($q) ?>" class="form-control"
                   placeholder="Имя дерева или подпись" aria-label="Имя дерева или подпись" autofocus>
            <button class="btn btn-success" type="submit"><i class="bi bi-search"></i> Найти</button>
        </div>
    </form>

    <?php if ($tooShort): ?>
        <div class="alert alert-info">Введите хотя бы 2 символа.</div>
    <?php elseif ($dataProvider === null): ?>
        <p class="text-muted">Введите имя дерева или подпись к посадке.</p>
    <?php elseif ($dataProvider->getTotalCount() === 0): ?>
        <div class="alert alert-light border">
            По запросу «<?= Html::encode($q) ?>» ничего не найдено. Проверьте написание или попробуйте часть имени.
        </div>
    <?php else: ?>
        <p class="text-muted">
            Найдено: <?= Yii::$app->formatter->asInteger($dataProvider->getTotalCount()) ?>
        </p>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4 g-3">
            <?php foreach ($dataProvider->getModels() as $tree): /** @var Planting $tree */ ?>
                <?php
                $cover = $tree->mainPhoto && $tree->mainPhoto->isApproved()
                    ? $tree->mainPhoto
                    : ($tree->approvedPhotos[count($tree->approvedPhotos) - 1] ?? null);
                ?>
                <div class="col">
                    <a href="<?= Html::encode($tree->getPublicUrl()) ?>" class="card h-100 search-card text-reset text-decoration-none">
                        <?php if ($cover): ?>
                            <img src="<?= Html::encode($cover->getThumbUrl()) ?>" alt="" class="card-img-top search-card__photo" loading="lazy">
                        <?php else: ?>
                            <div class="card-img-top search-card__photo search-card__photo--empty"><i class="bi bi-tree"></i></div>
                        <?php endif ?>
                        <div class="card-body">
                            <div class="d-flex align-items-start gap-2 mb-1">
                                <h2 class="h6 mb-0 flex-grow-1"><?= Html::encode($tree->title) ?></h2>
                                <?php if ($tree->isNamed()): ?><span class="badge text-bg-warning">Именное</span><?php endif ?>
                            </div>
                            <div class="small text-muted"><?= Html::encode($tree->species?->name) ?></div>
                            <?php if ($tree->planted_at): ?>
                                <div class="small text-muted">Посажено <?= Yii::$app->formatter->asDate($tree->planted_at, 'long') ?></div>
                            <?php endif ?>
                        </div>
                    </a>
                </div>
            <?php endforeach ?>
        </div>
        <div class="mt-4">
            <?= LinkPager::widget(['pagination' => $dataProvider->getPagination()]) ?>
        </div>
    <?php endif ?>
</div>
